<?php

namespace App\Services;

use App\Enums\JenisBayar;
use App\Enums\Role;
use App\Enums\Status;
use App\Enums\StatusIzin;
use App\Models\Dokumen;
use App\Models\HasilUji;
use App\Models\Lab;
use App\Models\Lhu;
use App\Models\Permohonan;
use App\Models\Setting;
use App\Models\StatusLog;
use App\Models\TahapLab;
use App\Models\User;
use App\Support\HariKerja;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Seluruh aturan alur permohonan ada di sini, bukan di controller:
 * transisi status atomik, perhitungan tagihan, rekap LHU, dan penyimpanan berkas.
 */
class Workflow
{
    public const MAX_UPLOAD = 5 * 1024 * 1024;
    public const DISK = 'local';
    public const DIR = 'dokumen';

    /** @var string[] berkas yang sudah ditulis dalam transaksi berjalan */
    private array $written = [];

    /** @return \Illuminate\Support\Collection<int, Lab> */
    public function labAktif()
    {
        return Lab::query()->aktif()->get();
    }

    public function tagihan(int $jumlahSampel): int
    {
        return (int) $this->labAktif()->sum('tarif') * $jumlahSampel + (int) Setting::get('biaya_admin');
    }

    /** Jalankan dalam transaksi; berkas yang telanjur ditulis dihapus bila gagal. */
    public function tx(callable $fn): mixed
    {
        $this->written = [];
        try {
            return DB::transaction($fn);
        } catch (\Throwable $e) {
            foreach ($this->written as $path) {
                Storage::disk(self::DISK)->delete($path);
            }
            if ($e instanceof QueryException && $this->isDuplicate($e)) {
                throw new WorkflowException('Data ini sudah tercatat sebelumnya. Muat ulang halaman.');
            }
            throw $e;
        }
    }

    /**
     * Transisi atomik: hanya berhasil jika status di database masih $from.
     * Mencegah klik ganda dan dua petugas memproses data yang sama bersamaan.
     */
    public function transisi(Permohonan $p, Status $from, array $set, string $keterangan, User $oleh): void
    {
        $affected = Permohonan::query()
            ->whereKey($p->getKey())
            ->where('status', $from->value)
            ->update($set + ['updated_at' => now()]);

        if ($affected !== 1) {
            throw new WorkflowException('Status permohonan sudah berubah. Muat ulang halaman untuk melihat kondisi terbaru.');
        }

        $p->refresh();
        $this->catat($p, $p->status, $keterangan, $oleh);
    }

    public function catat(Permohonan $p, Status $status, string $keterangan, ?User $oleh): void
    {
        StatusLog::query()->create([
            'permohonan_id' => $p->id,
            'status' => $status,
            'keterangan' => mb_substr($keterangan, 0, 500),
            'user_id' => $oleh?->id,
        ]);
    }

    public function ajukan(User $u, array $data, array $files): Permohonan
    {
        return $this->tx(function () use ($u, $data, $files) {
            $data['jenis_bayar'] ??= $u->jenisBayar()->value;
            $p = Permohonan::query()->create($data + ['user_id' => $u->id, 'status' => Status::Diajukan]);
            // Diingat sebagai pilihan awal pada pengajuan berikutnya.
            $u->forceFill(['tipe_pengguna' => $data['jenis_bayar']])->save();
            $p->update(['kode' => sprintf('PMH-%s-%05d', now()->format('Y'), $p->id)]);
            $this->simpanBerkas($files, $p, Dokumen::PENDUKUNG, $u);
            $this->catat($p, Status::Diajukan, 'Permohonan diajukan secara online', $u);

            return $p;
        });
    }

    /**
     * Sampel fisik sesuai.
     * - MANDIRI/SURVEILEN: terbitkan kode billing, pengguna bayar dulu.
     * - EKSTERNAL: sampel langsung masuk lab pertama; kode billing diterbitkan setelah LHU jadi.
     */
    public function verifikasiValid(Permohonan $p, ?string $kodeBilling, User $admin, ?JenisBayar $jenis = null): int
    {
        $jenis ??= $p->jenis_bayar ?? JenisBayar::Mandiri;
        $tagihan = $this->tagihan($p->jumlah_sampel);

        if ($jenis->bayarDiAwal()) {
            if (! $kodeBilling) {
                throw new WorkflowException('Isi kode billing SIMPONI untuk pengguna jasa yang bayar di awal.');
            }
            $this->tx(fn () => $this->transisi($p, Status::Diajukan,
                ['status' => Status::MenungguPembayaran->value, 'tagihan' => $tagihan, 'kode_billing' => $kodeBilling, 'jenis_bayar' => $jenis->value],
                'Sampel fisik terverifikasi. Tagihan '.rupiah($tagihan).", kode billing SIMPONI {$kodeBilling}", $admin));

            return $tagihan;
        }

        $first = $this->labAktif()->first() ?? throw new WorkflowException('Belum ada laboratorium aktif. Atur di menu Lab & tarif.');
        $this->tx(function () use ($p, $admin, $tagihan, $first, $jenis) {
            $this->transisi($p, Status::Diajukan,
                ['status' => Status::DiLab->value, 'current_lab_id' => $first->id, 'tagihan' => $tagihan, 'jenis_bayar' => $jenis->value],
                'Sampel fisik terverifikasi (pengguna jasa eksternal, bayar setelah LHU jadi). Tagihan '.rupiah($tagihan)
                .". Sampel diteruskan otomatis ke Lab {$first->nama}", $admin);
            $this->masukLab($p, $first);
        });

        return $tagihan;
    }

    /** Catat sampel masuk sebuah lab beserta batas norma waktunya. */
    public function masukLab(Permohonan $p, Lab $lab): TahapLab
    {
        $now = now();
        $tahap = TahapLab::query()->create([
            'permohonan_id' => $p->id, 'lab_id' => $lab->id, 'norma_hari' => $lab->norma_hari,
            'masuk_at' => $now, 'batas_at' => HariKerja::batas($now, $lab->norma_hari),
        ]);
        if (! $p->masuk_lab_at) {
            Permohonan::query()->whereKey($p->id)->update([
                'masuk_lab_at' => $now,
                'target_selesai_at' => HariKerja::batas($now, (int) Setting::get('norma_total_hari')),
            ]);
            $p->refresh();
        }

        return $tahap;
    }

    /** Tahap aktif di lab saat ini; dibuat bila belum ada (data lama sebelum fitur norma waktu). */
    private function tahapSaatIni(Permohonan $p, Lab $lab): TahapLab
    {
        return TahapLab::query()->where('permohonan_id', $p->id)->where('lab_id', $lab->id)->first()
            ?? $this->masukLab($p, $lab);
    }

    /** Operator menyatakan ikut menangani sampel di lab-nya; namanya tampil di detail permohonan. */
    public function tangani(Permohonan $p, User $petugas): void
    {
        if ($p->status !== Status::DiLab || $p->current_lab_id !== $petugas->lab_id) {
            throw new WorkflowException('Sampel ini tidak ada di antrean lab Anda.');
        }
        $lab = Lab::query()->findOrFail($p->current_lab_id);
        $this->tx(function () use ($p, $petugas, $lab) {
            $tahap = $this->tahapSaatIni($p, $lab);
            if ($tahap->operator()->whereKey($petugas->id)->exists()) {
                throw new WorkflowException('Anda sudah tercatat menangani sampel ini.');
            }
            $tahap->operator()->attach($petugas->id, ['created_at' => now()]);
            Permohonan::query()->whereKey($p->id)->update(['updated_at' => now()]);
            $this->catat($p, Status::DiLab, "Sampel ditangani {$petugas->nama} (Lab {$lab->nama})", $petugas);
        });
    }

    public function tolak(Permohonan $p, string $alasan, User $admin): void
    {
        $this->tx(fn () => $this->transisi($p, Status::Diajukan,
            ['status' => Status::Ditolak->value, 'alasan_tolak' => $alasan], "Ditolak: {$alasan}", $admin));
    }

    public function bayar(Permohonan $p, string $ntpn, UploadedFile $bukti, User $u): void
    {
        $this->tx(function () use ($p, $ntpn, $bukti, $u) {
            $this->transisi($p, Status::MenungguPembayaran,
                ['status' => Status::MenungguKonfirmasiBayar->value, 'ntpn' => $ntpn],
                "Bukti transfer SIMPONI diunggah (NTPN {$ntpn})", $u);
            $this->simpanBerkas([$bukti], $p, Dokumen::BUKTI_BAYAR, $u);
        });
    }

    /**
     * Dana masuk.
     * - MANDIRI  : sampel diteruskan ke lab pertama (mulai diuji). Mengembalikan lab tersebut.
     * - EKSTERNAL: pelunasan setelah LHU jadi => SELESAI, LHU bisa diunduh. Mengembalikan null.
     */
    public function terimaPembayaran(Permohonan $p, User $admin): ?Lab
    {
        if ($p->eksternal()) {
            $this->tx(fn () => $this->transisi($p, Status::MenungguKonfirmasiBayar, ['status' => Status::Selesai->value],
                'Pelunasan SIMPONI terkonfirmasi. LHU '.($p->lhu?->nomor ?? '').' dapat diunduh pengguna jasa', $admin));

            return null;
        }

        $first = $this->labAktif()->first() ?? throw new WorkflowException('Belum ada laboratorium aktif. Atur di menu Lab & tarif.');
        $this->tx(function () use ($p, $admin, $first) {
            $this->transisi($p, Status::MenungguKonfirmasiBayar,
                ['status' => Status::DiLab->value, 'current_lab_id' => $first->id],
                "Pembayaran SIMPONI terkonfirmasi masuk. Sampel diteruskan otomatis ke Lab {$first->nama}", $admin);
            $this->masukLab($p, $first);
        });

        return $first;
    }

    public function tolakPembayaran(Permohonan $p, string $catatan, User $admin): void
    {
        $this->tx(fn () => $this->transisi($p, Status::MenungguKonfirmasiBayar,
            ['status' => Status::MenungguPembayaran->value, 'ntpn' => null],
            'Bukti pembayaran ditolak'.($catatan !== '' ? ": {$catatan}" : ''), $admin));
    }

    /**
     * Petugas lab menginput hasil. Sampel diteruskan ke lab aktif berikutnya;
     * bila ini lab terakhir, sistem merekap: semua NEGATIF => LULUS, ada POSITIF => TIDAK LULUS.
     */
    public function inputHasil(Permohonan $p, User $petugas, string $hasil, ?string $catatan, array $files): ?Lab
    {
        if ($p->status !== Status::DiLab || $p->current_lab_id !== $petugas->lab_id) {
            throw new WorkflowException('Sampel ini tidak ada di antrean lab Anda.');
        }
        $cur = Lab::query()->findOrFail($p->current_lab_id);
        $next = $this->labAktif()->first(fn (Lab $l) => $l->urutan > $cur->urutan);

        return $this->tx(function () use ($p, $petugas, $hasil, $catatan, $files, $cur, $next) {
            HasilUji::query()->create([
                'permohonan_id' => $p->id, 'lab_id' => $cur->id, 'petugas_id' => $petugas->id,
                'hasil' => $hasil, 'catatan' => $catatan,
            ]);
            $this->simpanBerkas($files, $p, Dokumen::LAMPIRAN_LAB, $petugas, $cur->id);

            // Tutup tahap lab ini; penginput hasil otomatis tercatat sebagai operator yang menangani.
            $tahap = $this->tahapSaatIni($p, $cur);
            $tahap->operator()->syncWithoutDetaching([$petugas->id => ['created_at' => now()]]);
            $tahap->update(['selesai_at' => now()]);

            if ($next) {
                $this->transisiLab($p, $cur, ['current_lab_id' => $next->id],
                    "Uji {$cur->nama} selesai. Data diteruskan otomatis ke Lab {$next->nama}", $petugas);
                $this->masukLab($p, $next);

                return $next;
            }

            $positif = HasilUji::query()->where('permohonan_id', $p->id)->where('hasil', 'POSITIF')->exists();
            Lhu::query()->create(['permohonan_id' => $p->id, 'status_izin' => $positif ? StatusIzin::TidakLulus : StatusIzin::Lulus]);
            $this->transisiLab($p, $cur, ['status' => Status::MenungguValidasi->value, 'current_lab_id' => null],
                "Uji {$cur->nama} selesai. Seluruh hasil lab direkap otomatis, menunggu validasi LHU", $petugas);

            return null;
        });
    }

    /** Transisi antar-lab: dikunci juga pada lab saat ini supaya dua petugas tidak menginput bersamaan. */
    private function transisiLab(Permohonan $p, Lab $cur, array $set, string $ket, User $u): void
    {
        $affected = Permohonan::query()->whereKey($p->id)
            ->where('status', Status::DiLab->value)->where('current_lab_id', $cur->id)
            ->update($set + ['updated_at' => now()]);
        if ($affected !== 1) {
            throw new WorkflowException('Sampel sudah diproses petugas lain. Muat ulang halaman.');
        }
        $p->refresh();
        $this->catat($p, $p->status, $ket, $u);
    }

    /**
     * Terbitkan LHU bernomor.
     * - MANDIRI  : langsung SELESAI, LHU bisa diunduh.
     * - EKSTERNAL: LHU jadi, kode billing pelunasan diterbitkan; LHU baru bisa diunduh setelah lunas.
     */
    public function validasiLhu(Permohonan $p, User $admin, ?string $kodeBilling = null): Lhu
    {
        if ($p->eksternal() && ! $kodeBilling) {
            throw new WorkflowException('Isi kode billing SIMPONI untuk pelunasan pengguna jasa eksternal.');
        }

        return $this->tx(function () use ($p, $admin, $kodeBilling) {
            $lhu = Lhu::query()->where('permohonan_id', $p->id)->lockForUpdate()->first()
                ?? throw new WorkflowException('Rekap LHU belum tersedia.');
            $tahun = now()->format('Y');
            $urut = Lhu::query()->whereNotNull('nomor')->where('nomor', 'like', '%/'.$tahun)->count() + 1;
            $nomor = sprintf('%04d/%s/%s', $urut, Setting::get('prefix_lhu'), $tahun);
            $kode = strtoupper(bin2hex(random_bytes(6)));
            $lhu->fill(['nomor' => $nomor, 'kode_verifikasi' => $kode, 'validated_by' => $admin->id, 'validated_at' => now()]);
            [$hash, $sig] = $this->sidik($p, $lhu);
            $lhu->fill(['hash' => $hash, 'signature' => $sig])->save();

            if ($p->eksternal()) {
                $this->transisi($p, Status::MenungguValidasi,
                    ['status' => Status::MenungguPembayaran->value, 'kode_billing' => $kodeBilling],
                    "LHU {$nomor} jadi. Menunggu pelunasan ".rupiah($p->tagihan)." dengan kode billing SIMPONI {$kodeBilling}; LHU bisa diambil setelah lunas", $admin);
            } else {
                $this->transisi($p, Status::MenungguValidasi, ['status' => Status::Selesai->value],
                    "LHU {$nomor} terbit. Keputusan: ".$lhu->status_izin->keputusan($p->tujuan), $admin);
            }

            return $lhu;
        });
    }

    /**
     * Sidik isi LHU: SHA-256 atas isi kanonik + HMAC dengan APP_KEY.
     * Hash bisa dicocokkan siapa pun; HMAC membuktikan dokumen dibuat oleh server ini
     * sehingga perubahan langsung di database pun terdeteksi di halaman verifikasi.
     *
     * @return array{0:string,1:string}
     */
    public function sidik(Permohonan $p, Lhu $lhu): array
    {
        $hasil = HasilUji::query()->where('permohonan_id', $p->id)->orderBy('lab_id')
            ->get(['lab_id', 'hasil'])->map(fn ($h) => [$h->lab_id, $h->hasil])->all();
        $isi = json_encode([
            'nomor' => $lhu->nomor, 'kode' => $p->kode, 'verifikasi' => $lhu->kode_verifikasi,
            'sampel' => $p->nama_sampel, 'komoditas' => $p->jenis_komoditas, 'jumlah' => $p->jumlah_sampel,
            'tujuan' => $p->tujuan->value, 'status' => $lhu->status_izin->value, 'hasil' => $hasil,
            'validated_at' => $lhu->validated_at?->format('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
        $hash = hash('sha256', $isi);

        return [$hash, hash_hmac('sha256', $hash, (string) config('app.key'))];
    }

    public function lhuUtuh(Lhu $lhu): bool
    {
        if (! $lhu->hash || ! $lhu->signature) {
            return false;
        }
        [$hash, $sig] = $this->sidik($lhu->permohonan, $lhu);

        return hash_equals($lhu->hash, $hash) && hash_equals($lhu->signature, $sig);
    }

    /** Siapa boleh melihat permohonan (otorisasi tingkat objek, dipakai Policy). */
    public function bolehLihat(User $u, Permohonan $p): bool
    {
        return match ($u->role) {
            Role::Superadmin, Role::AdminLab => true,
            Role::PenggunaJasa => $p->user_id === $u->id,
            Role::PetugasLab => ($p->status === Status::DiLab && $p->current_lab_id === $u->lab_id)
                || HasilUji::query()->where('permohonan_id', $p->id)->where('lab_id', $u->lab_id)->exists(),
        };
    }

    /** Deteksi tipe dari magic bytes, bukan dari ekstensi atau MIME kiriman browser. */
    public static function sniff(string $path): ?array
    {
        $h = (string) @file_get_contents($path, false, null, 0, 8);

        return match (true) {
            str_starts_with($h, '%PDF-') => ['application/pdf', 'pdf'],
            $h === "\x89PNG\r\n\x1a\n" => ['image/png', 'png'],
            strlen($h) >= 3 && str_starts_with($h, "\xFF\xD8\xFF") => ['image/jpeg', 'jpg'],
            default => null,
        };
    }

    /** @param UploadedFile[] $files */
    public function simpanBerkas(array $files, Permohonan $p, string $jenis, User $u, ?int $labId = null): void
    {
        foreach ($files as $f) {
            if (! $f instanceof UploadedFile) {
                continue;
            }
            if (! $f->isValid()) {
                throw new WorkflowException('Unggahan berkas gagal. Coba pilih ulang berkasnya.');
            }
            if ($f->getSize() > self::MAX_UPLOAD) {
                throw new WorkflowException('Ukuran berkas maksimal 5 MB.');
            }
            $type = self::sniff($f->getRealPath());
            if (! $type) {
                throw new WorkflowException('Format berkas harus PDF, JPG, atau PNG.');
            }
            $stored = bin2hex(random_bytes(24)).'.'.$type[1];
            $path = self::DIR.'/'.$stored;
            $sha = hash_file('sha256', $f->getRealPath());
            $size = (int) $f->getSize();
            $diDb = (bool) config('sipelab.berkas_di_database');
            if (! $diDb) {
                Storage::disk(self::DISK)->putFileAs(self::DIR, $f, $stored);
                $this->written[] = $path;
            }

            $orig = preg_replace('/[^\pL\pN._\- ()]/u', '_', basename((string) $f->getClientOriginalName())) ?: 'berkas';
            $dok = Dokumen::query()->create([
                'permohonan_id' => $p->id, 'jenis' => $jenis, 'lab_id' => $labId, 'stored_name' => $stored,
                'original_name' => mb_substr($orig, 0, 150), 'mime' => $type[0], 'size' => $size,
                'sha256' => $sha, 'uploaded_by' => $u->id,
            ]);
            if ($diDb) {
                // Hosting tanpa disk permanen (Vercel): isi berkas disimpan di database, ikut transaksi.
                DB::table('dokumen_isi')->insert(['dokumen_id' => $dok->id, 'isi' => base64_encode((string) file_get_contents($f->getRealPath()))]);
            }
        }
    }

    private function isDuplicate(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[0] ?? '');
        $msg = $e->getMessage();

        return $code === '23000' || $code === '23505' || str_contains($msg, 'Duplicate') || str_contains($msg, 'UNIQUE constraint');
    }
}
