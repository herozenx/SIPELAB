<?php

namespace App\Services;

use App\Enums\JenisBayar;
use App\Enums\Role;
use App\Enums\Status;
use App\Enums\StatusIzin;
use App\Enums\Tujuan;
use App\Models\Dokumen;
use App\Models\HasilParameter;
use App\Models\HasilUji;
use App\Models\Lab;
use App\Models\Lhu;
use App\Models\ParameterUji;
use App\Models\Permohonan;
use App\Models\Sampel;
use App\Models\Setting;
use App\Models\StatusLog;
use App\Models\TahapLab;
use App\Models\User;
use App\Support\FormatLhp;
use App\Support\HariKerja;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Seluruh aturan alur permohonan ada di sini, bukan di controller:
 * transisi status atomik, kode sampel Buku Sampel, isian operator, pemeriksaan Administrasi,
 * penomoran LHP, dan penyimpanan berkas.
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

    /**
     * Pengguna jasa mengajukan produk yang akan diuji (nama produk + jumlah sampel).
     * Rincian sampel (mis. Air & Es Batu) dicatat petugas Balai saat sampel diterima di loket.
     *
     * @param  array{nama_sampel: string, jumlah_sampel: int, pelanggan?: string, alamat: string, jenis_bayar: string}  $data
     */
    public function ajukan(User $u, array $data, array $files): Permohonan
    {
        return $this->tx(function () use ($u, $data, $files) {
            $data['jenis_bayar'] ??= $u->jenisBayar()->value;
            $p = Permohonan::query()->create($data + [
                'user_id' => $u->id, 'status' => Status::Diajukan,
                'pelanggan' => $u->instansi ?: $u->nama,
            ]);
            $this->simpanSampel($p, [['jenis_sampel' => $data['nama_sampel']]]);
            // Diingat sebagai pilihan awal pada pengajuan berikutnya.
            $u->forceFill(['tipe_pengguna' => $data['jenis_bayar']])->save();
            $p->update(['kode' => sprintf('PMH-%s-%05d', now()->format('Y'), $p->id)]);
            $this->simpanBerkas($files, $p, Dokumen::PENDUKUNG, $u);
            $this->catat($p, Status::Diajukan, 'Permohonan diajukan secara online', $u);

            return $p;
        });
    }

    /**
     * Admin mencatat sampel internal Balai (Monitoring, Test Result, Uji Banding):
     * tanpa pengguna jasa & tanpa tagihan, kode sampel langsung terbit dan sampel masuk lab pertama.
     *
     * @param  array{jenis_bayar:string, pelanggan:string, alamat:string, no_surat?:?string, cp?:?string, keterangan?:?string, sampel: array<int, array{jenis_sampel:string, berat?:?string}>}  $data
     */
    public function ajukanInternal(User $admin, array $data): Permohonan
    {
        $jenis = JenisBayar::from($data['jenis_bayar']);
        if (! $jenis->internal()) {
            throw new WorkflowException('Layanan ini diajukan sendiri oleh pengguna jasa.');
        }
        $first = $this->labAktif()->first() ?? throw new WorkflowException('Belum ada laboratorium aktif. Atur di menu Lab & tarif.');

        return $this->tx(function () use ($admin, $data, $jenis, $first) {
            $sampel = array_values($data['sampel']);
            $p = Permohonan::query()->create([
                'user_id' => $admin->id, 'status' => Status::Diajukan, 'jenis_bayar' => $jenis->value,
                'pelanggan' => $data['pelanggan'], 'alamat' => $data['alamat'],
                'no_surat' => $data['no_surat'] ?? null, 'cp' => $data['cp'] ?? null, 'keterangan' => $data['keterangan'] ?? null,
                'subkontrak' => implode(', ', (array) ($data['subkontrak'] ?? [])) ?: null,
                'nama_sampel' => mb_substr(implode(', ', array_column($sampel, 'jenis_sampel')), 0, 120),
                'jenis_komoditas' => mb_substr($sampel[0]['jenis_sampel'], 0, 80),
                'asal_sampel' => mb_substr($data['alamat'], 0, 150),
                'tujuan' => Tujuan::Domestik->value,
                'jumlah_sampel' => count($sampel),
            ]);
            $p->update(['kode' => sprintf('PMH-%s-%05d', now()->format('Y'), $p->id)]);
            $this->simpanSampel($p, $sampel);
            $this->catat($p, Status::Diajukan, "Sampel internal {$jenis->label()} dicatat Admin", $admin);
            $kode = $this->terbitkanKodeSampel($p, $jenis);
            $this->transisi($p, Status::Diajukan,
                ['status' => Status::DiLab->value, 'current_lab_id' => $first->id, 'tagihan' => 0],
                "Kode sampel {$kode} terbit. Sampel diteruskan otomatis ke Lab {$first->nama}", $admin);
            $this->masukLab($p, $first);

            return $p;
        });
    }

    /** @param array<int, array{jenis_sampel:string, berat?:?string}> $rows */
    private function simpanSampel(Permohonan $p, array $rows): void
    {
        foreach (array_values($rows) as $i => $r) {
            Sampel::query()->create([
                'permohonan_id' => $p->id, 'urut' => $i + 1,
                'jenis_sampel' => mb_substr(trim($r['jenis_sampel']), 0, 150),
                'berat' => isset($r['berat']) && trim((string) $r['berat']) !== '' ? mb_substr(trim($r['berat']), 0, 30) : null,
            ]);
        }
    }

    /**
     * Kode sampel Buku Sampel: SP.{layanan}/{ddmmyy}.{NN}, NN = urutan sampel hari itu (semua layanan),
     * kembali ke 01 setiap hari. Lebih dari satu sampel dalam permohonan: huruf A, B, C, ...
     */
    public function terbitkanKodeSampel(Permohonan $p, JenisBayar $jenis): string
    {
        $tgl = now();
        $urut = (int) Permohonan::query()->where('kode_tanggal', $tgl->toDateString())->max('kode_urut') + 1;
        $kode = sprintf('SP.%s/%s.%02d', $jenis->kodeSampel(), $tgl->format('dmy'), $urut);
        Permohonan::query()->whereKey($p->id)->update([
            'kode_sampel' => $kode, 'kode_tanggal' => $tgl->toDateString(), 'kode_urut' => $urut, 'diterima_at' => $tgl,
        ]);
        $sampel = Sampel::query()->where('permohonan_id', $p->id)->orderBy('urut')->get();
        foreach ($sampel as $i => $s) {
            $s->update(['kode' => $sampel->count() > 1 ? $kode.chr(65 + $i) : $kode]);
        }
        $p->refresh();

        return $kode;
    }

    /**
     * Sampel fisik sesuai: petugas loket mencatat rincian sampel (jenis & berat per baris), kode sampel terbit.
     * - MANDIRI/SURVEILEN: terbitkan kode billing, pengguna bayar dulu.
     * - EKSTERNAL: sampel langsung masuk lab pertama; kode billing diterbitkan setelah LHP jadi.
     *
     * @param  array{sampel?: ?array<int, array{jenis_sampel:string, berat?:?string}>, no_surat?:?string, cp?:?string}  $penerimaan
     */
    public function verifikasiValid(Permohonan $p, ?string $kodeBilling, User $admin, ?JenisBayar $jenis = null, array $penerimaan = []): int
    {
        $jenis ??= $p->jenis_bayar ?? JenisBayar::Mandiri;
        if ($jenis->internal()) {
            throw new WorkflowException('Sampel internal dicatat lewat menu Sampel internal.');
        }
        $tagihan = $this->tagihan(! empty($penerimaan['sampel']) ? count($penerimaan['sampel']) : $p->jumlah_sampel);
        if ($jenis->bayarDiAwal() && ! $kodeBilling) {
            throw new WorkflowException('Isi kode billing SIMPONI untuk pengguna jasa yang bayar di awal.');
        }
        $first = $jenis->bayarDiAwal() ? null
            : ($this->labAktif()->first() ?? throw new WorkflowException('Belum ada laboratorium aktif. Atur di menu Lab & tarif.'));

        $this->tx(function () use ($p, $admin, $tagihan, $first, $jenis, $kodeBilling, $penerimaan) {
            $this->lengkapiPenerimaan($p, $penerimaan);
            $kode = $this->terbitkanKodeSampel($p, $jenis);
            if ($jenis->bayarDiAwal()) {
                $this->transisi($p, Status::Diajukan,
                    ['status' => Status::MenungguPembayaran->value, 'tagihan' => $tagihan, 'kode_billing' => $kodeBilling, 'jenis_bayar' => $jenis->value],
                    "Sampel fisik terverifikasi, kode sampel {$kode}. Tagihan ".rupiah($tagihan).", kode billing SIMPONI {$kodeBilling}", $admin);

                return;
            }
            $this->transisi($p, Status::Diajukan,
                ['status' => Status::DiLab->value, 'current_lab_id' => $first->id, 'tagihan' => $tagihan, 'jenis_bayar' => $jenis->value],
                "Sampel fisik terverifikasi, kode sampel {$kode} (pengguna jasa eksternal, bayar setelah LHP jadi). Tagihan ".rupiah($tagihan)
                .". Sampel diteruskan otomatis ke Lab {$first->nama}", $admin);
            $this->masukLab($p, $first);
        });

        return $tagihan;
    }

    private function lengkapiPenerimaan(Permohonan $p, array $penerimaan): void
    {
        // Rincian sampel dari petugas loket menggantikan baris awal (belum ada isian lab pada tahap ini).
        if (! empty($penerimaan['sampel'])) {
            Sampel::query()->where('permohonan_id', $p->id)->delete();
            $this->simpanSampel($p, array_values($penerimaan['sampel']));
            Permohonan::query()->whereKey($p->id)->update(['jumlah_sampel' => count($penerimaan['sampel'])]);
        }
        $set = array_filter([
            'pelanggan' => isset($penerimaan['pelanggan']) ? mb_substr(trim((string) $penerimaan['pelanggan']), 0, 150) : null,
            'alamat' => isset($penerimaan['alamat']) ? mb_substr(trim((string) $penerimaan['alamat']), 0, 300) : null,
            'no_surat' => isset($penerimaan['no_surat']) ? trim((string) $penerimaan['no_surat']) : null,
            'cp' => isset($penerimaan['cp']) ? trim((string) $penerimaan['cp']) : null,
            // Kolom EKSTERNAL Buku Sampel: lab subkontrak (Angler, LPPMHP, Saraswati).
            'subkontrak' => isset($penerimaan['subkontrak']) ? mb_substr(trim((string) $penerimaan['subkontrak']), 0, 60) : null,
        ], fn ($v) => $v !== null && $v !== '');
        if ($set) {
            Permohonan::query()->whereKey($p->id)->update($set);
        }
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
    /** Operator mengerjakan lab-nya sendiri; Admin Lab (kepala tim operator) lab mana pun tempat sampel berada. */
    public function bolehIsiLab(Permohonan $p, User $u): bool
    {
        return $p->status === Status::DiLab && $p->current_lab_id
            && ($u->role === Role::AdminLab || ($u->role === Role::PetugasLab && $p->current_lab_id === $u->lab_id));
    }

    public function tangani(Permohonan $p, User $petugas): void
    {
        if (! $this->bolehIsiLab($p, $petugas)) {
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
     * - EKSTERNAL: pelunasan setelah LHP jadi => SELESAI, LHP bisa diunduh. Mengembalikan null.
     */
    public function terimaPembayaran(Permohonan $p, User $admin): ?Lab
    {
        if ($p->eksternal()) {
            $this->tx(fn () => $this->transisi($p, Status::MenungguKonfirmasiBayar, ['status' => Status::Selesai->value],
                'Pelunasan SIMPONI terkonfirmasi. LHP '.($p->lhu?->nomor ?? '').' dapat diunduh pengguna jasa', $admin));

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
     * Operator lab mencentang parameter yang diuji per sampel dan mengisi hasil tiap ulangan.
     * Sampel lalu diteruskan ke lab aktif berikutnya; setelah lab terakhir (atau bila sampel
     * dikembalikan Administrasi) masuk pemeriksaan Administrasi.
     *
     * @param  array<int|string, array<int|string, array{ulangan?: array<int,?string>, persyaratan?: ?string, metode?: ?string, keterangan?: ?string, melebihi?: mixed}>>  $isian  [sampel_id => [parameter_id => isian]]
     * @return Lab|null lab berikutnya, atau null bila sampel masuk pemeriksaan Administrasi
     */
    public function inputHasil(Permohonan $p, User $petugas, array $isian, bool $tanpaUji, ?string $catatan, array $files): ?Lab
    {
        if (! $this->bolehIsiLab($p, $petugas)) {
            throw new WorkflowException('Sampel ini tidak ada di antrean lab Anda.');
        }
        $cur = Lab::query()->findOrFail($p->current_lab_id);
        $baris = $tanpaUji ? [] : $this->susunIsian($p, $cur, $isian, $petugas);
        if (! $tanpaUji && ! $baris) {
            throw new WorkflowException('Centang minimal satu parameter yang diuji, atau pilih "Tidak ada uji di lab ini".');
        }
        $next = $p->dikembalikan ? null : $this->labAktif()->first(fn (Lab $l) => $l->urutan > $cur->urutan);

        return $this->tx(function () use ($p, $petugas, $baris, $catatan, $files, $cur, $next) {
            $sampelIds = Sampel::query()->where('permohonan_id', $p->id)->pluck('id');
            HasilParameter::query()->whereIn('sampel_id', $sampelIds)->where('lab_id', $cur->id)->delete();
            foreach ($baris as $b) {
                HasilParameter::query()->create($b);
            }
            $ringkas = ! $baris ? HasilUji::TANPA_UJI
                : (collect($baris)->contains(fn ($b) => $b['melebihi']) ? HasilUji::MELEBIHI : HasilUji::MEMENUHI);
            HasilUji::query()->updateOrCreate(
                ['permohonan_id' => $p->id, 'lab_id' => $cur->id],
                ['petugas_id' => $petugas->id, 'hasil' => $ringkas, 'catatan' => $catatan],
            );
            $this->simpanBerkas($files, $p, Dokumen::LAMPIRAN_LAB, $petugas, $cur->id);

            // Tutup tahap lab ini; penginput hasil otomatis tercatat sebagai operator yang menangani.
            $tahap = $this->tahapSaatIni($p, $cur);
            $tahap->operator()->syncWithoutDetaching([$petugas->id => ['created_at' => now()]]);
            $tahap->update(['selesai_at' => now()]);

            $jumlah = count($baris);
            $ket = $jumlah ? "{$jumlah} isian parameter" : 'tidak ada uji di lab ini';
            if ($next) {
                $this->transisiLab($p, $cur, ['current_lab_id' => $next->id],
                    "Uji {$cur->nama} selesai ({$ket}). Data diteruskan otomatis ke Lab {$next->nama}", $petugas);
                $this->masukLab($p, $next);

                return $next;
            }

            if (! HasilParameter::query()->whereIn('sampel_id', $sampelIds)->exists()) {
                throw new WorkflowException('Belum ada satu pun parameter yang diuji di semua lab. Isi parameter yang diuji sebelum menyimpan.');
            }
            $this->rekapLhu($p);
            $ulang = $p->dikembalikan;
            $this->transisiLab($p, $cur, ['status' => Status::MenungguCekAdministrasi->value, 'current_lab_id' => null, 'dikembalikan' => false],
                $ulang ? "Lab {$cur->nama} memperbaiki isian ({$ket}). Diteruskan kembali ke Administrasi untuk diperiksa"
                    : "Uji {$cur->nama} selesai ({$ket}). Seluruh isian lab diteruskan ke Administrasi untuk diperiksa", $petugas);

            return null;
        });
    }

    /** Validasi isian operator terhadap katalog lab ini & sampel permohonan; hasilkan baris siap simpan. */
    private function susunIsian(Permohonan $p, Lab $lab, array $isian, User $petugas): array
    {
        $params = ParameterUji::query()->where('lab_id', $lab->id)->where('aktif', true)->get()->keyBy('id');
        $sampel = Sampel::query()->where('permohonan_id', $p->id)->get()->keyBy('id');
        $baris = [];
        foreach ($isian as $sid => $perParam) {
            $s = $sampel->get((int) $sid) ?? throw new WorkflowException('Data sampel tidak dikenal. Muat ulang halaman.');
            foreach ((array) $perParam as $pid => $d) {
                if (empty($d['diuji'])) {
                    continue;
                }
                $param = $params->get((int) $pid) ?? throw new WorkflowException('Parameter tidak dikenal untuk lab ini. Muat ulang halaman.');
                $nama = $param->namaPolos();
                $label = $s->kode ?: $s->jenis_sampel;
                $teksLhp = $param->nama;
                $rincian = null;
                if ($param->sub_item) {
                    // Sensory test: nilai per sub-item seperti LHPS Organoleptik.
                    $sub = $param->susunSubItem((array) ($d['sub'] ?? []))
                        ?? throw new WorkflowException("Isi minimal satu nilai {$nama} untuk sampel {$label}.");
                    [$teksLhp, $ulangan, $rincian] = [$sub['nama'], $sub['ulangan'], $sub['rincian']];
                } else {
                    $ulangan = array_values(array_filter(array_map(fn ($v) => mb_substr(trim((string) $v), 0, 60), (array) ($d['ulangan'] ?? [])), fn ($v) => $v !== ''));
                }
                if (! $ulangan) {
                    throw new WorkflowException("Isi hasil {$nama} untuk sampel {$label}.");
                }
                if (count($ulangan) > 20) {
                    throw new WorkflowException("Ulangan {$nama} maksimal 20.");
                }
                $teks = fn ($k, $max) => ($v = trim((string) ($d[$k] ?? ''))) !== '' ? mb_substr($v, 0, $max) : null;
                $baris[] = [
                    'sampel_id' => $s->id, 'parameter_id' => $param->id, 'lab_id' => $lab->id, 'petugas_id' => $petugas->id,
                    'ulangan' => $ulangan, 'rincian' => $rincian, 'bidang' => $param->bidang, 'bidang_en' => $param->bidang_en, 'nama' => $teksLhp,
                    // Persyaratan/metode/keterangan dari katalog; persyaratan boleh dipilih operator bila katalog punya beberapa opsi.
                    'satuan' => $param->satuan,
                    'persyaratan' => $teks('persyaratan', 150) ?? ($param->persyaratan[0] ?? null),
                    'metode' => $teks('metode', 150) ?? $param->metode,
                    'keterangan' => $teks('keterangan', 150) ?? $param->keterangan,
                    'melebihi' => ! empty($d['melebihi']), 'urutan' => $param->urutan,
                ];
            }
        }

        return $baris;
    }

    /** Rekap internal (tidak dicetak di LHP): ada parameter melebihi persyaratan => TIDAK_LULUS. */
    private function rekapLhu(Permohonan $p): void
    {
        $melebihi = HasilParameter::query()->whereIn('sampel_id', Sampel::query()->where('permohonan_id', $p->id)->select('id'))
            ->where('melebihi', true)->exists();
        Lhu::query()->updateOrCreate(['permohonan_id' => $p->id], ['status_izin' => $melebihi ? StatusIzin::TidakLulus : StatusIzin::Lulus]);
    }

    /**
     * Administrasi memeriksa semua isian operator & file LHP.
     * - sesuai     : diteruskan ke Admin Lab untuk diterbitkan.
     * - kembalikan : sampel kembali ke lab yang dipilih; setelah diperbaiki langsung kembali ke Administrasi.
     */
    public function cekAdministrasi(Permohonan $p, User $adm, string $keputusan, ?int $labId, ?string $catatan): ?Lab
    {
        $catatan = $catatan !== null && trim($catatan) !== '' ? mb_substr(trim($catatan), 0, 500) : null;
        if ($keputusan === 'sesuai') {
            $this->tx(function () use ($p, $adm, $catatan) {
                $this->transisi($p, Status::MenungguCekAdministrasi, ['status' => Status::MenungguValidasi->value],
                    'Diperiksa Administrasi: isian operator dan file LHP sesuai. Menunggu penerbitan LHP oleh Admin Lab', $adm);
                Lhu::query()->where('permohonan_id', $p->id)->update(['cek_by' => $adm->id, 'cek_at' => now(), 'catatan_cek' => $catatan]);
            });

            return null;
        }

        $lab = Lab::query()->aktif()->whereKey($labId)->first() ?? throw new WorkflowException('Pilih lab yang perlu memperbaiki isian.');
        if (! $catatan) {
            throw new WorkflowException('Tulis bagian yang perlu diperbaiki supaya operator tahu apa yang harus diubah.');
        }
        $this->tx(function () use ($p, $adm, $lab, $catatan) {
            $this->transisi($p, Status::MenungguCekAdministrasi,
                ['status' => Status::DiLab->value, 'current_lab_id' => $lab->id, 'dikembalikan' => true],
                "Dikembalikan Administrasi ke Lab {$lab->nama}: {$catatan}", $adm);
            Lhu::query()->where('permohonan_id', $p->id)->update(['cek_by' => $adm->id, 'cek_at' => now(), 'catatan_cek' => $catatan]);
            $tahap = TahapLab::query()->where('permohonan_id', $p->id)->where('lab_id', $lab->id)->first();
            $tahap ? $tahap->update(['selesai_at' => null]) : $this->masukLab($p, $lab);
        });

        return $lab;
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
     * Terbitkan LHP bernomor (format per layanan di Pengaturan) dengan penandatangan pilihan Admin.
     * - MANDIRI/SURVEILEN & sampel internal : langsung SELESAI.
     * - EKSTERNAL : LHP jadi, kode billing pelunasan diterbitkan; LHP baru bisa diunduh setelah lunas.
     *
     * @param  array{nama:string, nip:string, jabatan:string}  $ttd
     */
    public function validasiLhu(Permohonan $p, User $admin, ?string $kodeBilling, array $ttd): Lhu
    {
        if ($p->eksternal() && ! $kodeBilling) {
            throw new WorkflowException('Isi kode billing SIMPONI untuk pelunasan pengguna jasa eksternal.');
        }
        if (! in_array($ttd['jabatan'] ?? null, [Lhu::JABATAN_KEPALA, Lhu::JABATAN_PJ], true) || trim($ttd['nama'] ?? '') === '') {
            throw new WorkflowException('Pilih penandatangan dan jabatannya.');
        }

        return $this->tx(function () use ($p, $admin, $kodeBilling, $ttd) {
            $lhu = Lhu::query()->where('permohonan_id', $p->id)->lockForUpdate()->first()
                ?? throw new WorkflowException('Rekap LHP belum tersedia.');
            $now = now();
            $urut = (int) Lhu::query()->where('tahun', $now->year)->max('urut') + 1;
            $nomor = strtr(Setting::get(($p->jenis_bayar ?? JenisBayar::Mandiri)->kunciNomor()), [
                '{NNNN}' => sprintf('%04d', $urut), '{BULAN}' => FormatLhp::romawi($now->month), '{TAHUN}' => $now->year,
            ]);
            $kode = strtoupper(bin2hex(random_bytes(6)));
            $lhu->fill([
                'nomor' => $nomor, 'tahun' => $now->year, 'urut' => $urut, 'kode_verifikasi' => $kode,
                'validated_by' => $admin->id, 'validated_at' => $now,
                'ttd_nama' => mb_substr(trim($ttd['nama']), 0, 100), 'ttd_nip' => mb_substr(trim((string) $ttd['nip']), 0, 30), 'ttd_jabatan' => $ttd['jabatan'],
            ]);
            [$hash, $sig] = $this->sidik($p, $lhu);
            $lhu->fill(['hash' => $hash, 'signature' => $sig])->save();

            if ($p->eksternal()) {
                $this->transisi($p, Status::MenungguValidasi,
                    ['status' => Status::MenungguPembayaran->value, 'kode_billing' => $kodeBilling],
                    "LHP {$nomor} jadi. Menunggu pelunasan ".rupiah($p->tagihan)." dengan kode billing SIMPONI {$kodeBilling}; LHP bisa diambil setelah lunas", $admin);
            } else {
                $this->transisi($p, Status::MenungguValidasi, ['status' => Status::Selesai->value], "LHP {$nomor} terbit", $admin);
            }

            return $lhu;
        });
    }

    /**
     * Sidik isi LHP: SHA-256 atas isi kanonik + HMAC dengan APP_KEY.
     * Hash bisa dicocokkan siapa pun; HMAC membuktikan dokumen dibuat oleh server ini
     * sehingga perubahan langsung di database pun terdeteksi di halaman verifikasi.
     * LHU lama (sebelum isian parameter, tanpa kolom urut) tetap memakai rumus lama.
     *
     * @return array{0:string,1:string}
     */
    public function sidik(Permohonan $p, Lhu $lhu): array
    {
        if ($lhu->urut === null) {
            $hasil = HasilUji::query()->where('permohonan_id', $p->id)->orderBy('lab_id')
                ->get(['lab_id', 'hasil'])->map(fn ($h) => [$h->lab_id, $h->hasil])->all();
            $isi = [
                'nomor' => $lhu->nomor, 'kode' => $p->kode, 'verifikasi' => $lhu->kode_verifikasi,
                'sampel' => $p->nama_sampel, 'komoditas' => $p->jenis_komoditas, 'jumlah' => $p->jumlah_sampel,
                'tujuan' => $p->tujuan->value, 'status' => $lhu->status_izin->value, 'hasil' => $hasil,
                'validated_at' => $lhu->validated_at?->format('Y-m-d H:i:s'),
            ];
        } else {
            $sampel = Sampel::query()->where('permohonan_id', $p->id)->orderBy('urut')->with('hasil')->get()
                ->map(fn ($s) => [$s->kode, $s->jenis_sampel, $s->hasil->map(fn ($h) => [
                    $h->bidang, $h->nama, $h->ulangan, $h->satuan, $h->persyaratan, $h->metode, $h->keterangan,
                ])->all()])->all();
            $isi = [
                'nomor' => $lhu->nomor, 'kode' => $p->kode, 'kode_sampel' => $p->kode_sampel, 'verifikasi' => $lhu->kode_verifikasi,
                'pelanggan' => $p->pelanggan, 'alamat' => $p->alamat, 'layanan' => $p->jenis_bayar?->value,
                'diterima' => $p->diterima_at?->format('Y-m-d'), 'sampel' => $sampel,
                'ttd' => [$lhu->ttd_nama, $lhu->ttd_nip, $lhu->ttd_jabatan],
                'validated_at' => $lhu->validated_at?->format('Y-m-d H:i:s'),
            ];
        }
        $hash = hash('sha256', json_encode($isi, JSON_UNESCAPED_UNICODE));

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
            Role::Superadmin, Role::AdminLab, Role::Administrasi => true,
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
