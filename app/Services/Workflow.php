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
use App\Models\ParameterUji;
use App\Models\Pengembalian;
use App\Models\Permohonan;
use App\Models\PermohonanParameter;
use App\Models\PermohonanProduk;
use App\Models\Persetujuan;
use App\Models\Setting;
use App\Models\StatusLog;
use App\Models\SuratTugas;
use App\Models\TahapLab;
use App\Models\User;
use App\Support\HariKerja;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Seluruh aturan alur permohonan ada di sini, bukan di controller:
 * transisi status atomik, tagihan, registrasi sampel, surat tugas, hasil per parameter,
 * pemeriksaan-paraf-pengesahan LHP, pengembalian ke lab, dan penyimpanan berkas.
 */
class Workflow
{
    public const MAX_UPLOAD = 5 * 1024 * 1024;
    /** Produk per permohonan: akhiran kode A sampai J. */
    public const MAKS_PRODUK = 10;
    public const DISK = 'local';
    public const DIR = 'dokumen';

    /** @var string[] berkas yang sudah ditulis dalam transaksi berjalan */
    private array $written = [];

    /** @return Collection<int, Lab> */
    public function labAktif()
    {
        return Lab::query()->aktif()->get();
    }

    /** @return Collection<int, ParameterUji> */
    public function parameterAktif()
    {
        return ParameterUji::query()->aktif()->with('lab')->get();
    }

    /** Perkiraan tagihan: tarif tiap parameter × jumlah pengujian, ditambah biaya administrasi. */
    public function tagihan(Permohonan $p): int
    {
        $p->loadMissing('parameter');

        return (int) $p->parameter->sum(fn (PermohonanParameter $x) => $x->subtotal()) + (int) Setting::get('biaya_admin');
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

    /** Kunci baris permohonan selama transaksi (dua lab/penyelia menyelesaikan bersamaan). */
    private function kunci(Permohonan $p): Permohonan
    {
        $fresh = Permohonan::query()->whereKey($p->id)->lockForUpdate()->firstOrFail();
        $p->setRawAttributes($fresh->getAttributes(), true);

        return $p;
    }

    private function pastikanStatus(Permohonan $p, Status ...$boleh): void
    {
        if (! in_array($p->status, $boleh, true)) {
            throw new WorkflowException('Status permohonan sudah berubah. Muat ulang halaman untuk melihat kondisi terbaru.');
        }
    }

    // =====================================================================================
    // 1. Pengajuan (pengguna jasa: Ekspor/Test Result, Uji Mandiri, Uji Banding)
    // =====================================================================================

    /**
     * Ajukan permohonan berisi satu atau beberapa produk (satu perusahaan, hari yang sama, beda produk).
     * Nomor permohonan sementara (PMH-DDMMYY-NNN); kode sampel SP baru diberikan saat sampel diregistrasi
     * supaya nomor urut harian buku sampel tidak terpakai oleh permohonan yang ditolak atau tidak datang.
     *
     * @param  array<int, array{nama:string, jumlah:int, parameter:array<int,int>}>  $produk  [ [nama, jumlah, [parameter_id => jumlah pengujian]] ]
     * @param  UploadedFile[]  $files
     */
    public function ajukan(User $u, array $data, array $produk, array $files, JenisBayar $jenis = JenisBayar::Mandiri): Permohonan
    {
        $produk = array_values(array_filter($produk, fn ($x) => ! empty($x['parameter'])));
        if ($produk === []) {
            throw new WorkflowException('Pilih minimal satu parameter uji.');
        }
        if (count($produk) > self::MAKS_PRODUK) {
            throw new WorkflowException('Satu permohonan paling banyak '.self::MAKS_PRODUK.' produk.');
        }
        $idDiminta = collect($produk)->flatMap(fn ($x) => array_keys($x['parameter']))->unique()->all();
        $master = ParameterUji::query()->aktif()->whereIn('parameter_uji.id', $idDiminta)->get()->keyBy('id');
        if ($master->isEmpty()) {
            throw new WorkflowException('Pilih minimal satu parameter uji.');
        }

        return $this->tx(function () use ($u, $data, $produk, $files, $master, $jenis) {
            $komoditas = collect($produk)->pluck('komoditas')->map(fn ($k) => trim((string) $k))->filter()->unique()->join(', ');
            $p = Permohonan::query()->create([
                'nama_sampel' => mb_substr(collect($produk)->pluck('nama')->join(', '), 0, 120),
                'jumlah_sampel' => min(65535, collect($produk)->sum(fn ($x) => max(1, (int) $x['jumlah']))),
                'jenis_komoditas' => mb_substr($komoditas !== '' ? $komoditas : (string) ($data['jenis_komoditas'] ?? '-'), 0, 80),
                'tujuan' => $jenis->tujuan(),
            ] + $data + [
                'user_id' => $u->id, 'status' => Status::Diajukan, 'jenis_bayar' => $jenis,
                'pemohon_setuju_at' => now(),
            ]);
            $tgl = now()->format('dmy');
            $this->beriNomor($p, 'kode', fn () => $this->nomorPermohonanBaru($tgl));
            // Kode sampel SP langsung diberikan saat diajukan (masukan Balai). Saat registrasi kode ini dipakai
            // bila jenis layanan & tanggal terima sama; bila berbeda, diberi nomor baru sesuai buku sampel.
            $this->beriNomor($p, 'kode_sampel', fn () => $this->kodeSampelBaru($jenis, $tgl));
            foreach ($produk as $i => $x) {
                $pr = PermohonanProduk::query()->create([
                    'permohonan_id' => $p->id, 'urut' => $i + 1, 'nama' => mb_substr(trim((string) $x['nama']), 0, 120),
                    'jumlah' => max(1, min(500, (int) $x['jumlah'])),
                    'komoditas' => ($x['komoditas'] ?? '') !== '' ? mb_substr(trim((string) $x['komoditas']), 0, 80) : null,
                    'berat' => ($x['berat'] ?? '') !== '' ? mb_substr(trim((string) $x['berat']), 0, 30) : null,
                ]);
                foreach ($x['parameter'] as $id => $jumlah) {
                    $m = $master->get($id);
                    if (! $m) {
                        continue;
                    }
                    PermohonanParameter::query()->create([
                        'permohonan_id' => $p->id, 'produk_id' => $pr->id, 'parameter_id' => $m->id, 'lab_id' => $m->lab_id,
                        'kode' => $m->kode, 'nama' => $m->nama, 'bidang' => $m->bidang, 'metode' => $m->metode,
                        'persyaratan' => $m->persyaratan, 'satuan' => $m->satuan, 'akreditasi' => $m->akreditasi,
                        'jumlah' => max(1, min(500, (int) $jumlah)), 'tarif' => $m->tarif, 'keterangan' => $m->keterangan,
                    ]);
                }
            }
            if (! PermohonanParameter::query()->where('permohonan_id', $p->id)->exists()) {
                throw new WorkflowException('Pilih minimal satu parameter uji.');
            }
            $this->kodeProduk($p, (string) $p->kode_sampel);
            if (! empty($data['alamat']) && ! $u->alamat) {
                $u->forceFill(['alamat' => $data['alamat']])->save();
            }
            $this->simpanBerkas($files, $p, Dokumen::PENDUKUNG, $u);
            $this->catat($p, Status::Diajukan, "Permohonan {$p->kode_sampel} diajukan secara online ({$jenis->label()}). Pemohon menyetujui metode, biaya, dan waktu pengujian", $u);

            return $p;
        });
    }

    // =====================================================================================
    // 2. Verifikasi permohonan (Administrator) & registrasi sampel (Operator/Admin Lab)
    // =====================================================================================

    /** Kesimpulan kaji ulang permintaan (FPPCU FL/OK/SUB II/8.5.2.a). */
    public const KESIMPULAN = [
        'dapat' => 'Permintaan dapat dilayani',
        'tidak' => 'Permintaan tidak dapat dilayani',
        'eksternal' => 'Laboratorium eksternal (terlampir)',
    ];

    /**
     * Verifikasi + kaji ulang permintaan: personel, bahan uji, dan alat uji siap; kesimpulan dapat dilayani
     * atau dirujuk ke laboratorium eksternal. Permohonan lalu diterima dan menunggu sampel diregistrasi.
     */
    public function verifikasi(Permohonan $p, User $admin, string $kesimpulan = 'dapat', ?string $catatan = null): void
    {
        $kaji = ['personel' => true, 'bahan' => true, 'alat' => true, 'kesimpulan' => $kesimpulan,
            'catatan' => $catatan ? mb_substr($catatan, 0, 500) : null, 'oleh' => $admin->id, 'at' => now()->toIso8601String()];
        $teks = $kesimpulan === 'eksternal'
            ? 'Permohonan diterima; sebagian pengujian dirujuk ke laboratorium eksternal'.($catatan ? " ({$catatan})" : '').'.'
            : 'Permohonan diterima: kaji ulang permintaan selesai (personel, bahan, dan alat uji siap).';
        $this->tx(fn () => $this->transisi($p, Status::Diajukan, ['status' => Status::Diterima->value, 'kaji_ulang' => json_encode($kaji)],
            $teks.' Silakan serahkan sampel ke loket untuk diregistrasi', $admin));
    }

    /**
     * Sampel fisik diterima: Operator/Admin Lab menetapkan jenis layanan, sistem meregistrasi sampel
     * (kode sampel otomatis). Mandiri menunggu billing; lainnya langsung siap diuji (bayar di belakang).
     *
     * @param  array{bentuk?:?string, kemasan?:?string, suhu?:?string, berat?:?string}  $kondisi
     */
    public function registrasi(Permohonan $p, User $operator, JenisBayar $jenis, array $kondisi = []): string
    {
        return $this->tx(function () use ($p, $operator, $jenis, $kondisi) {
            $this->kunci($p);
            $this->pastikanStatus($p, Status::Diterima);
            $tagihan = $this->tagihan($p);
            $tgl = now()->format('dmy');
            $ke = $jenis->bayarDiAwal() ? Status::MenungguBilling : Status::SiapUji;
            // Kode SP yang diberikan saat diajukan dipakai bila jenis layanan & tanggal terima cocok;
            // data versi sebelumnya menyimpannya di kolom kode.
            $awalan = $jenis->kodeSurat()."/{$tgl}.";
            $cocok = collect([(string) $p->kode_sampel, (string) $p->kode])->first(fn ($k) => str_starts_with($k, $awalan));
            // Jenis layanan diganti petugas di hari yang sama: nomor urut tetap, hanya awalannya berubah.
            if ($cocok === null && preg_match('#^SP\.[A-Z]+/'.$tgl.'\.(\d+)$#', (string) $p->kode_sampel, $m)) {
                $cocok = $awalan.$m[1];
            }
            if ($cocok !== null) {
                Permohonan::query()->whereKey($p->id)->update(['kode_sampel' => $cocok]);
                $p->kode_sampel = $cocok;
            } else {
                $this->beriNomor($p, 'kode_sampel', fn () => $this->kodeSampelBaru($jenis, $tgl));
            }
            $kode = (string) $p->kode_sampel;
            $this->beriAgenda($p);
            $this->kodeProduk($p, $kode);

            $this->transisi($p, Status::Diterima, [
                'status' => $ke->value, 'jenis_bayar' => $jenis->value, 'tujuan' => $jenis->tujuan()->value,
                'tagihan' => $tagihan, 'diterima_at' => now(), 'diterima_oleh' => $operator->id,
            ] + array_map(fn ($v) => $v === '' ? null : $v, $kondisi),
                'Sampel diterima dan diregistrasi dengan kode '.$kode.' (No. agenda '.$p->no_agenda.'). '
                ."Tujuan permohonan: {$jenis->label()}. "
                .($jenis->bayarDiAwal() ? 'Menunggu penerbitan kode billing SIMPONI.' : 'Dibayar di belakang; siap dibuatkan surat tugas.'), $operator);

            return $kode;
        });
    }

    /**
     * Isi kolom bernomor urut ($kolom unik di database). Bila dua proses mendapat nomor yang sama,
     * unique index menolak salah satunya dan nomor dihitung ulang (savepoint, transaksi luar tetap utuh).
     */
    private function beriNomor(Permohonan $p, string $kolom, callable $nomorBaru): void
    {
        for ($coba = 1; ; $coba++) {
            try {
                DB::transaction(fn () => Permohonan::query()->whereKey($p->id)->update([$kolom => $nomorBaru()]));
                $p->{$kolom} = Permohonan::query()->whereKey($p->id)->value($kolom);

                return;
            } catch (QueryException $e) {
                if ($coba >= 5 || ! $this->isDuplicate($e)) {
                    throw $e;
                }
            }
        }
    }

    /** Nomor permohonan online sebelum sampel diregistrasi: PMH-DDMMYY-NNN (urut harian). */
    public function nomorPermohonanBaru(string $tgl): string
    {
        $awalan = "PMH-{$tgl}-";
        $terakhir = Permohonan::query()->where('kode', 'like', $awalan.'%')->pluck('kode')
            ->map(fn (string $k) => (int) substr($k, strlen($awalan)))->max() ?? 0;

        return sprintf('%s%03d', $awalan, $terakhir + 1);
    }

    /**
     * Kode sampel mengikuti buku sampel: {SP.M|SP.PE|SP.S}/DDMMYY.NN.
     * Nomor urut NN per hari dan berlanjut lintas jenis layanan, mis. SP.E/060126.01–.03 lalu SP.S/060126.04.
     */
    public function kodeSampelBaru(JenisBayar $jenis, string $tgl): string
    {
        // Periksa kode & kode_sampel: data versi sebelumnya menyimpan kode SP di kolom kode.
        $pola = 'SP.%/'.$tgl.'.%';
        $terakhir = Permohonan::query()->where('kode', 'like', $pola)->pluck('kode')
            ->merge(Permohonan::query()->where('kode_sampel', 'like', $pola)->pluck('kode_sampel'))
            ->map(fn (string $k) => preg_match('#^SP\.[A-Z]+/'.$tgl.'\.(\d+)#', $k, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return sprintf('%s/%s.%02d', $jenis->kodeSurat(), $tgl, $terakhir + 1);
    }

    /**
     * Nomor agenda ("NO. surat" buku sampel), urut per tahun sejak registrasi. Nomor ini menjadi nomor urut LHP.
     * Dimulai setelah nomor LHP terbesar tahun itu supaya tidak bentrok dengan LHP yang terbit sebelum revisi ini.
     */
    private function beriAgenda(Permohonan $p): void
    {
        if ($p->no_agenda) {
            return;
        }
        $tahun = (int) now()->format('Y');
        Permohonan::query()->whereKey($p->id)->update(['tahun_agenda' => $tahun]);
        $this->beriNomor($p, 'no_agenda', fn () => max(
            (int) Permohonan::query()->where('tahun_agenda', $tahun)->max('no_agenda'),
            (int) Lhu::query()->where('tahun', $tahun)->max('nomor_urut'),
        ) + 1);
        $p->tahun_agenda = $tahun;
    }

    /** Kode tiap produk: satu produk = kode sampel; beberapa produk = kode sampel + A, B, C, ... (seperti di LHP). */
    private function kodeProduk(Permohonan $p, string $kode): void
    {
        $produk = PermohonanProduk::query()->where('permohonan_id', $p->id)->orderBy('urut')->get();
        foreach ($produk as $x) {
            $x->update(['kode' => $produk->count() > 1 ? $kode.PermohonanProduk::akhiran($x->urut) : $kode]);
        }
    }

    public function tolak(Permohonan $p, string $alasan, User $admin): void
    {
        $kaji = json_encode(['kesimpulan' => 'tidak', 'catatan' => $alasan, 'oleh' => $admin->id, 'at' => now()->toIso8601String()]);
        $this->tx(function () use ($p, $alasan, $admin, $kaji) {
            // Kode SP dilepas agar nomor urut buku sampel bisa dipakai permohonan berikutnya.
            $this->transisi($p, Status::Diajukan,
                ['status' => Status::Ditolak->value, 'alasan_tolak' => $alasan, 'kaji_ulang' => $kaji, 'kode_sampel' => null],
                "Ditolak (permintaan tidak dapat dilayani): {$alasan}", $admin);
            PermohonanProduk::query()->where('permohonan_id', $p->id)->update(['kode' => null]);
        });
    }

    // =====================================================================================
    // 3. Billing & pembayaran (Operator Bendahara)
    // =====================================================================================

    /** Bendahara menerbitkan billing SIMPONI resmi (Mandiri di awal; lainnya setelah LHP disahkan). */
    /**
     * Bendahara menerbitkan billing SIMPONI resmi. Sistem membuat Bukti Tagihan PNBP otomatis dari data ini
     * (kode billing, tanggal & kedaluwarsa, nomor aju/PNBP, detail tarif per parameter).
     *
     * @param  array{billing_at?:mixed, billing_kedaluwarsa_at?:mixed, nomor_aju?:?string, nomor_pnbp?:?string}  $data
     */
    public function terbitkanBilling(Permohonan $p, string $kodeBilling, int $tagihan, ?UploadedFile $berkas, User $bendahara, array $data = []): void
    {
        $this->tx(function () use ($p, $kodeBilling, $tagihan, $berkas, $bendahara, $data) {
            $tgl = isset($data['billing_at']) && $data['billing_at'] ? \Illuminate\Support\Carbon::parse($data['billing_at']) : now();
            $this->transisi($p, Status::MenungguBilling,
                ['status' => Status::MenungguPembayaran->value, 'kode_billing' => $kodeBilling, 'tagihan' => $tagihan,
                    'billing_at' => $tgl, 'billing_kedaluwarsa_at' => ! empty($data['billing_kedaluwarsa_at']) ? \Illuminate\Support\Carbon::parse($data['billing_kedaluwarsa_at']) : $tgl->copy()->addDays(3),
                    'nomor_aju' => ($data['nomor_aju'] ?? null) ?: null, 'nomor_pnbp' => ($data['nomor_pnbp'] ?? null) ?: null,
                    'dibayar_at' => null, 'bank_bayar' => null, 'channel_bayar' => null, 'ntb' => null, 'ntpn' => null],
                ($p->lhpTerbit() ? 'LHP sudah disahkan. ' : '').'Kode billing SIMPONI '.$kodeBilling.' diterbitkan, tagihan '.rupiah($tagihan), $bendahara);
            if ($berkas) {
                $this->simpanBerkas([$berkas], $p, Dokumen::BILLING, $bendahara);
            }
        });
    }

    /** Pengguna jasa mengunggah bukti bayar; NTPN tidak perlu diketik manual. */
    public function bayar(Permohonan $p, UploadedFile $bukti, User $u): void
    {
        $this->tx(function () use ($p, $bukti, $u) {
            $this->transisi($p, Status::MenungguPembayaran,
                ['status' => Status::MenungguKonfirmasiBayar->value], 'Bukti pembayaran SIMPONI diunggah pengguna jasa', $u);
            $this->simpanBerkas([$bukti], $p, Dokumen::BUKTI_BAYAR, $u);
        });
    }

    /**
     * Dana masuk. Sebelum diuji (Mandiri) => siap dibuatkan surat tugas.
     * Setelah LHP disahkan (bayar di belakang) => selesai.
     */
    /**
     * @param  array{dibayar_at?:mixed, bank_bayar?:?string, channel_bayar?:?string, ntb?:?string}  $bayar  data pembayaran SIMPONI
     */
    public function terimaPembayaran(Permohonan $p, User $bendahara, ?string $ntpn = null, array $bayar = []): Status
    {
        $ke = $p->lhpTerbit() ? Status::Selesai : Status::SiapUji;
        $this->tx(fn () => $this->transisi($p, Status::MenungguKonfirmasiBayar,
            ['status' => $ke->value, 'ntpn' => $ntpn ?: null,
                'dibayar_at' => ! empty($bayar['dibayar_at']) ? \Illuminate\Support\Carbon::parse($bayar['dibayar_at']) : now(),
                'bank_bayar' => ($bayar['bank_bayar'] ?? null) ?: null, 'channel_bayar' => ($bayar['channel_bayar'] ?? null) ?: null, 'ntb' => ($bayar['ntb'] ?? null) ?: null],
            'Pembayaran SIMPONI terkonfirmasi'.($ntpn ? " (NTPN {$ntpn})" : '').'. '
            .($ke === Status::Selesai ? 'LHP selesai dan dapat diunduh' : 'Sampel siap dibuatkan surat tugas pengujian'), $bendahara));

        return $ke;
    }

    public function tolakPembayaran(Permohonan $p, string $catatan, User $bendahara): void
    {
        $this->tx(fn () => $this->transisi($p, Status::MenungguKonfirmasiBayar,
            ['status' => Status::MenungguPembayaran->value, 'ntpn' => null],
            'Bukti pembayaran ditolak'.($catatan !== '' ? ": {$catatan}" : ''), $bendahara));
    }

    // =====================================================================================
    // 4. Surat tugas (Admin Laboratorium) & pengujian (Analis/Penyelia)
    // =====================================================================================

    /**
     * Terbitkan surat tugas pengujian (template FL/OK/SUB II/8.5.3.d): satu surat per sampel dengan
     * No. FPPC dari kode sampel, menugaskan penyelia setiap bidang yang parameternya diminta.
     * Sampel lalu masuk ke lab-lab itu bersamaan (paralel).
     *
     * @param  array<int, int|null>  $penyelia  [lab_id => user_id penyelia]
     * @return Collection<int, SuratTugas>
     */
    public function buatSuratTugas(Permohonan $p, User $operator, array $penyelia = [], ?string $catatan = null): Collection
    {
        return $this->tx(function () use ($p, $operator, $penyelia, $catatan) {
            $this->kunci($p);
            $this->pastikanStatus($p, Status::SiapUji);
            $p->load('parameter');
            $labs = Lab::query()->whereIn('id', $p->labTerlibat())->orderBy('urutan')->get();
            if ($labs->isEmpty()) {
                throw new WorkflowException('Permohonan ini belum punya parameter uji.');
            }

            $surat = collect();
            $nomor = $this->nomorSuratTugas($p);
            $catatan = $catatan !== null && trim($catatan) !== '' ? mb_substr(trim($catatan), 0, 1000) : null;
            foreach ($labs as $lab) {
                $pid = $penyelia[$lab->id] ?? null;
                $pj = $pid ? User::query()->whereKey($pid)->where('role', Role::Penyelia->value)->where('lab_id', $lab->id)->where('aktif', true)->first() : null;
                if ($pid && ! $pj) {
                    throw new WorkflowException("Penyelia yang dipilih untuk Lab {$lab->nama} tidak valid.");
                }
                $pj ??= $lab->penyelia()->first();
                $surat->push(SuratTugas::query()->create([
                    'permohonan_id' => $p->id, 'lab_id' => $lab->id, 'nomor' => $nomor,
                    'penyelia_id' => $pj?->id, 'dibuat_oleh' => $operator->id, 'catatan' => $catatan,
                ]));
                $this->masukLab($p, $lab);
            }
            $this->transisi($p, Status::SiapUji, ['status' => Status::DiLab->value],
                "Surat tugas pengujian {$nomor} diterbitkan untuk bidang ".$labs->pluck('nama')->join(', ', ' dan ').'. Sampel mulai diuji', $operator);

            return $surat;
        });
    }

    /** No. FPPC surat tugas, mis. SP.S/050126.02/FPPCU/SUB II/I/2026 (format di Pengaturan). */
    private function nomorSuratTugas(Permohonan $p): string
    {
        $urut = SuratTugas::query()->whereYear('created_at', now()->year)->distinct()->count('permohonan_id') + 1;

        return $this->formatNomor(Setting::get('format_nomor_surat_tugas'), $urut, now(), ['{kode_sampel}' => (string) $p->kode_sampel]);
    }

    /** Ganti {urut} (4 digit), {bulan} (Romawi), {tahun}, dan tambahan lain pada format nomor dari Pengaturan. */
    public function formatNomor(string $format, int $urut, \DateTimeInterface $tgl, array $tambahan = []): string
    {
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][(int) $tgl->format('n') - 1];

        return strtr($format, $tambahan + ['{urut}' => sprintf('%04d', $urut), '{bulan}' => $romawi, '{tahun}' => $tgl->format('Y')]);
    }

    /** Catat sampel masuk sebuah lab beserta batas norma waktunya (dibuka ulang bila sudah ada). */
    public function masukLab(Permohonan $p, Lab $lab): TahapLab
    {
        $now = now();
        $tahap = TahapLab::query()->updateOrCreate(['permohonan_id' => $p->id, 'lab_id' => $lab->id], [
            'norma_hari' => $lab->norma_hari, 'masuk_at' => $now,
            'batas_at' => HariKerja::batas($now, $lab->norma_hari), 'selesai_at' => null,
        ]);
        if (! $p->masuk_lab_at) {
            Permohonan::query()->whereKey($p->id)->update([
                'masuk_lab_at' => $now,
                'target_selesai_at' => HariKerja::batas($now, (int) Setting::get('norma_total_hari')),
            ]);
            $p->masuk_lab_at = $now;
        }

        return $tahap;
    }

    /** Tahap lab yang sedang berjalan untuk lab petugas ini, atau null. */
    public function tahapTerbuka(Permohonan $p, ?int $labId): ?TahapLab
    {
        if ($p->status !== Status::DiLab || ! $labId) {
            return null;
        }

        return TahapLab::query()->where('permohonan_id', $p->id)->where('lab_id', $labId)->whereNull('selesai_at')->first();
    }

    /** Analis menyatakan ikut menangani sampel di lab-nya; namanya tampil di detail permohonan. */
    public function tangani(Permohonan $p, User $petugas): void
    {
        $tahap = $this->tahapTerbuka($p, $petugas->lab_id) ?? throw new WorkflowException('Sampel ini tidak ada di antrean lab Anda.');
        $this->tx(function () use ($p, $petugas, $tahap) {
            if ($tahap->operator()->whereKey($petugas->id)->exists()) {
                throw new WorkflowException('Anda sudah tercatat menangani sampel ini.');
            }
            $tahap->operator()->attach($petugas->id, ['created_at' => now()]);
            Permohonan::query()->whereKey($p->id)->update(['updated_at' => now()]);
            $this->catat($p, Status::DiLab, "Sampel ditangani {$petugas->nama} (Lab {$petugas->lab?->nama})", $petugas);
        });
    }

    /**
     * Disposisi: penyelia menugaskan analis lab-nya untuk tiap parameter (Lembar Disposisi).
     * Analis yang ditugaskan ikut tercatat menangani sampel.
     *
     * @param  array<int, int|null>  $analis  [permohonan_parameter_id => user_id analis | null]
     */
    public function disposisi(Permohonan $p, User $penyelia, array $analis): int
    {
        if ($penyelia->role !== Role::Penyelia) {
            throw new WorkflowException('Hanya penyelia yang dapat membuat disposisi analis.');
        }
        $tahap = $this->tahapTerbuka($p, $penyelia->lab_id) ?? throw new WorkflowException('Sampel ini tidak ada di antrean lab Anda.');

        return $this->tx(function () use ($p, $penyelia, $analis, $tahap) {
            $boleh = User::query()->where('role', Role::PetugasLab->value)->where('lab_id', $penyelia->lab_id)->where('aktif', true)->pluck('nama', 'id');
            $baris = PermohonanParameter::query()->where('permohonan_id', $p->id)->where('lab_id', $penyelia->lab_id)->get();
            $ubah = 0;
            foreach ($baris as $b) {
                if (! array_key_exists($b->id, $analis)) {
                    continue;
                }
                $id = $analis[$b->id] ? (int) $analis[$b->id] : null;
                if ($id !== null && ! $boleh->has($id)) {
                    throw new WorkflowException('Analis yang dipilih bukan analis aktif di lab Anda.');
                }
                if ($b->analis_id !== $id) {
                    $b->update(['analis_id' => $id]);
                    $ubah++;
                }
            }
            $dipakai = $baris->fresh()->pluck('analis_id')->filter()->unique();
            if ($dipakai->isNotEmpty()) {
                $tahap->operator()->syncWithoutDetaching($dipakai->mapWithKeys(fn ($id) => [$id => ['created_at' => now()]])->all());
            }
            Permohonan::query()->whereKey($p->id)->update(['updated_at' => now()]);
            if ($ubah > 0) {
                $this->catat($p, Status::DiLab, "Disposisi Lab {$penyelia->lab?->nama} oleh {$penyelia->nama}: "
                    .$dipakai->map(fn ($id) => $boleh[$id] ?? '?')->join(', '), $penyelia);
            }

            return $ubah;
        });
    }

    /**
     * Simpan hasil parameter lab petugas. Bila $selesai, semua parameter lab itu wajib terisi,
     * tahap lab ditutup, dan bila seluruh lab sudah selesai sampel masuk pemeriksaan LHP.
     *
     * @param  array<int, array{hasil?:?string, memenuhi?:mixed, keterangan?:?string}>  $hasil  [permohonan_parameter_id => ...]
     * @param  UploadedFile[]  $files
     * @return bool true bila seluruh pengujian selesai (masuk pemeriksaan)
     */
    public function simpanHasil(Permohonan $p, User $petugas, array $hasil, array $files, bool $selesai): bool
    {
        if (! $petugas->isLab()) {
            throw new WorkflowException('Hanya analis atau penyelia lab yang dapat menginput hasil.');
        }

        return $this->tx(function () use ($p, $petugas, $hasil, $files, $selesai) {
            $this->kunci($p);
            $lab = $petugas->lab ?? throw new WorkflowException('Akun Anda belum ditempatkan di lab.');
            $tahap = $this->tahapTerbuka($p, $lab->id) ?? throw new WorkflowException('Sampel ini tidak ada di antrean lab Anda.');

            $baris = PermohonanParameter::query()->where('permohonan_id', $p->id)->where('lab_id', $lab->id)->get();
            foreach ($baris as $b) {
                $in = $hasil[$b->id] ?? null;
                // Parameter yang didisposisikan ke analis lain hanya diisi analis itu (atau penyelia).
                if (! is_array($in) || ($petugas->role === Role::PetugasLab && $b->analis_id && $b->analis_id !== $petugas->id)) {
                    continue;
                }
                $nilai = trim((string) ($in['hasil'] ?? ''));
                $memenuhi = match ((string) ($in['memenuhi'] ?? '')) {
                    '1' => true, '0' => false, default => null,
                };
                $ket = trim((string) ($in['keterangan'] ?? ''));
                $nilai = $nilai !== '' ? mb_substr($nilai, 0, 150) : null;
                $ket = $ket !== '' ? mb_substr($ket, 0, 300) : null;
                if ($b->hasil !== $nilai || $b->memenuhi !== $memenuhi || $b->keterangan !== $ket) {
                    $b->fill(['hasil' => $nilai, 'memenuhi' => $memenuhi, 'keterangan' => $ket,
                        'diinput_oleh' => $petugas->id, 'diinput_at' => now()])->save();
                }
            }
            $this->simpanBerkas($files, $p, Dokumen::LAMPIRAN_LAB, $petugas, $lab->id);
            $tahap->operator()->syncWithoutDetaching([$petugas->id => ['created_at' => now()]]);
            Permohonan::query()->whereKey($p->id)->update(['updated_at' => now()]);

            if (! $selesai) {
                return false;
            }
            $kurang = $baris->fresh()->reject->sudahDiisi();
            if ($kurang->isNotEmpty()) {
                throw new WorkflowException('Lengkapi hasil dan kesesuaian persyaratan untuk: '.$kurang->pluck('nama')->join(', ').'.');
            }
            $tahap->update(['selesai_at' => now()]);
            Pengembalian::query()->where('permohonan_id', $p->id)->where('lab_id', $lab->id)->whereNull('selesai_at')->update(['selesai_at' => now()]);

            $masihBuka = TahapLab::query()->where('permohonan_id', $p->id)->whereNull('selesai_at')->exists();
            if ($masihBuka) {
                $this->catat($p, Status::DiLab, "Pengujian Lab {$lab->nama} selesai", $petugas);

                return false;
            }
            $this->transisi($p, Status::DiLab, ['status' => Status::MenungguPemeriksaan->value, 'current_lab_id' => null],
                "Pengujian Lab {$lab->nama} selesai. Seluruh hasil lengkap, menunggu pemeriksaan LHP oleh Admin Laboratorium", $petugas);

            return true;
        });
    }

    // =====================================================================================
    // 5. Pemeriksaan (Admin Lab) -> Paraf (Penyelia) -> Pengesahan (Penanggung Jawab)
    // =====================================================================================

    /** Admin Lab menyatakan seluruh data LHP sudah sesuai dengan permohonan. */
    public function periksa(Permohonan $p, User $admin, ?string $catatan = null): void
    {
        $this->tx(function () use ($p, $admin, $catatan) {
            $this->kunci($p);
            $this->pastikanStatus($p, Status::MenungguPemeriksaan);
            $this->setujui($p, Persetujuan::PERIKSA, null, $admin, $catatan);
            Pengembalian::query()->where('permohonan_id', $p->id)->where('jenis', Pengembalian::PERBAIKAN)->whereNull('selesai_at')->update(['selesai_at' => now()]);
            $this->transisi($p, Status::MenungguPemeriksaan, ['status' => Status::MenungguParaf->value],
                'Hasil pengujian divalidasi Administrator: sesuai permohonan dan lengkap. Menunggu paraf penyelia', $admin);
        });
    }

    /** Penyelia memaraf hasil bidangnya. Bila semua bidang sudah diparaf, LHP menunggu pengesahan. */
    public function paraf(Permohonan $p, User $penyelia): bool
    {
        return $this->tx(function () use ($p, $penyelia) {
            $this->kunci($p);
            $this->pastikanStatus($p, Status::MenungguParaf);
            $p->load('parameter');
            $labs = $p->labTerlibat();
            if ($penyelia->role !== Role::Penyelia || ! $labs->contains($penyelia->lab_id)) {
                throw new WorkflowException('Anda bukan penyelia bidang yang menguji sampel ini.');
            }
            if (Persetujuan::query()->where('permohonan_id', $p->id)->where('jenis', Persetujuan::PARAF)->where('lab_id', $penyelia->lab_id)->exists()) {
                throw new WorkflowException('Hasil bidang Anda sudah diparaf.');
            }
            $this->setujui($p, Persetujuan::PARAF, $penyelia->lab_id, $penyelia);
            $sudah = Persetujuan::query()->where('permohonan_id', $p->id)->where('jenis', Persetujuan::PARAF)->pluck('lab_id');
            if ($labs->diff($sudah)->isEmpty()) {
                $this->transisi($p, Status::MenungguParaf, ['status' => Status::MenungguPengesahan->value],
                    "Diparaf {$penyelia->nama} (Penyelia {$penyelia->lab?->nama}). Semua bidang sudah diparaf, menunggu pengesahan", $penyelia);

                return true;
            }
            Permohonan::query()->whereKey($p->id)->update(['updated_at' => now()]);
            $this->catat($p, Status::MenungguParaf, "Diparaf {$penyelia->nama} (Penyelia {$penyelia->lab?->nama})", $penyelia);

            return false;
        });
    }

    /**
     * Penanggung Jawab mengesahkan: LHP bernomor (urut agenda per tahun), kode verifikasi + QR.
     * Mandiri => selesai. Lainnya => menunggu billing (bayar di belakang).
     */
    public function sahkan(Permohonan $p, User $pj): Lhu
    {
        if ($pj->role !== Role::PenanggungJawab) {
            throw new WorkflowException('Hanya Penanggung Jawab Laboratorium yang dapat mengesahkan LHP.');
        }

        return $this->tx(function () use ($p, $pj) {
            $this->kunci($p);
            $this->pastikanStatus($p, Status::MenungguPengesahan);
            $sidik = $this->sidikHasil($p);
            $kadaluarsa = Persetujuan::query()->where('permohonan_id', $p->id)->where('sidik', '!=', $sidik)->exists();
            if ($kadaluarsa) {
                throw new WorkflowException('Hasil uji berubah setelah diperiksa/diparaf. Kembalikan ke lab untuk diperiksa ulang.');
            }
            $this->setujui($p, Persetujuan::SAH, null, $pj);

            // Nomor LHP = nomor agenda buku sampel; bulan Romawi = bulan sampel diterima (bukan bulan pengesahan).
            // Contoh dokumen Balai: SP.E/290626.02, No. agenda 437, disahkan 1 Juli -> 0437/.../VI/2026.
            if ($p->no_agenda) {
                $tahun = (int) $p->tahun_agenda;
                $urut = (int) $p->no_agenda;
            } else {
                // Diregistrasi sebelum ada nomor agenda: ambil nomor berikutnya.
                $tahun = (int) ($p->diterima_at ?? now())->format('Y');
                $urut = max(
                    (int) Lhu::query()->where('tahun', $tahun)->max('nomor_urut'),
                    (int) Permohonan::query()->where('tahun_agenda', $tahun)->max('no_agenda'),
                ) + 1;
            }
            $nomor = $this->formatNomor(Setting::get('format_nomor_lhp'), $urut, $p->diterima_at ?? now());
            $memenuhi = PermohonanParameter::query()->where('permohonan_id', $p->id)->where('memenuhi', false)->doesntExist();
            $lhu = Lhu::query()->firstOrNew(['permohonan_id' => $p->id]);
            $lhu->fill([
                'nomor' => $nomor, 'tahun' => $tahun, 'nomor_urut' => $urut, 'versi_sidik' => 4,
                'status_izin' => $memenuhi ? StatusIzin::Lulus : StatusIzin::TidakLulus,
                'kode_verifikasi' => strtoupper(bin2hex(random_bytes(6))), 'validated_by' => $pj->id, 'validated_at' => now(),
            ]);
            $lhu->setRelation('permohonan', $p);
            [$hash, $sig] = $this->sidik($p, $lhu);
            $lhu->fill(['hash' => $hash, 'signature' => $sig])->save();
            $p->setRelation('lhu', $lhu);

            $ke = $p->mandiri() ? Status::Selesai : Status::MenungguBilling;
            $this->transisi($p, Status::MenungguPengesahan, ['status' => $ke->value],
                "LHP {$nomor} disahkan oleh {$pj->nama}. "
                .($ke === Status::Selesai ? 'LHP selesai dan dapat diambil di kantor Balai' : 'Menunggu billing SIMPONI (dibayar di belakang)'), $pj);

            return $lhu;
        });
    }

    private function setujui(Permohonan $p, string $jenis, ?int $labId, User $u, ?string $catatan = null): void
    {
        Persetujuan::query()->create([
            'permohonan_id' => $p->id, 'jenis' => $jenis, 'lab_id' => $labId, 'user_id' => $u->id,
            'catatan' => $catatan ? mb_substr($catatan, 0, 500) : null, 'sidik' => $this->sidikHasil($p), 'created_at' => now(),
        ]);
    }

    /** Jenis pengembalian yang boleh dipilih pada status tertentu (sesuai flowchart). */
    public static function pilihanKembali(Status $status): array
    {
        return match (true) {
            // Validasi hasil (Administrator): data belum benar/lengkap -> kembali ke pengujian.
            $status === Status::MenungguPemeriksaan => [Pengembalian::REVISI, Pengembalian::UJI_ULANG],
            // Paraf/validasi akhir tidak disetujui: perbaikan data -> Administrator; pengujian ulang -> lab.
            in_array($status, [Status::MenungguParaf, Status::MenungguPengesahan], true) => [Pengembalian::PERBAIKAN, Pengembalian::UJI_ULANG],
            default => [],
        };
    }

    /**
     * Kembalikan proses sebelum LHP disahkan. Pemeriksaan & paraf sebelumnya gugur.
     * - REVISI / UJI_ULANG: kembali ke lab yang bersangkutan, lalu divalidasi ulang.
     * - PERBAIKAN: kembali ke validasi hasil oleh Administrator.
     */
    public function kembalikan(Permohonan $p, User $oleh, ?int $labId, string $jenis, string $catatan): void
    {
        $this->tx(function () use ($p, $oleh, $labId, $jenis, $catatan) {
            $this->kunci($p);
            if (! in_array($jenis, self::pilihanKembali($p->status), true)) {
                throw new WorkflowException('Pilihan pengembalian ini tidak berlaku pada tahap sekarang. Muat ulang halaman.');
            }
            if ($jenis === Pengembalian::PERBAIKAN) {
                Pengembalian::query()->create([
                    'permohonan_id' => $p->id, 'lab_id' => null, 'jenis' => $jenis, 'catatan' => mb_substr($catatan, 0, 1000),
                    'oleh' => $oleh->id, 'dari_status' => $p->status->value, 'created_at' => now(),
                ]);
                Persetujuan::query()->where('permohonan_id', $p->id)->delete();
                $this->transisi($p, $p->status, ['status' => Status::MenungguPemeriksaan->value],
                    "Hasil belum disetujui, perlu perbaikan data. Dikembalikan ke validasi hasil (Administrator) oleh {$oleh->nama}: {$catatan}", $oleh);

                return;
            }
            $p->load('parameter');
            $labId = (int) $labId;
            if (! $p->labTerlibat()->contains($labId)) {
                throw new WorkflowException('Lab tersebut tidak menguji sampel ini.');
            }
            if ($oleh->role === Role::Penyelia && $oleh->lab_id !== $labId) {
                throw new WorkflowException('Penyelia hanya dapat mengembalikan ke lab bidangnya sendiri.');
            }
            $lab = Lab::query()->findOrFail($labId);
            $baris = PermohonanParameter::query()->where('permohonan_id', $p->id)->where('lab_id', $labId)->get();

            $lama = null;
            if ($jenis === Pengembalian::UJI_ULANG) {
                $lama = $baris->map->only(['id', 'nama', 'hasil', 'memenuhi', 'keterangan', 'diinput_oleh', 'diinput_at'])->values()->all();
                PermohonanParameter::query()->whereIn('id', $baris->pluck('id'))
                    ->update(['hasil' => null, 'memenuhi' => null, 'diinput_oleh' => null, 'diinput_at' => null]);
                $this->masukLab($p, $lab); // norma waktu dihitung ulang
            } else {
                TahapLab::query()->where('permohonan_id', $p->id)->where('lab_id', $labId)->update(['selesai_at' => null]);
            }
            Pengembalian::query()->create([
                'permohonan_id' => $p->id, 'lab_id' => $labId, 'jenis' => $jenis, 'catatan' => mb_substr($catatan, 0, 1000),
                'oleh' => $oleh->id, 'dari_status' => $p->status->value, 'data_lama' => $lama, 'created_at' => now(),
            ]);
            Persetujuan::query()->where('permohonan_id', $p->id)->delete();

            $label = $jenis === Pengembalian::UJI_ULANG ? 'uji ulang sampel' : 'revisi data';
            $this->transisi($p, $p->status, ['status' => Status::DiLab->value],
                "Dikembalikan ke Lab {$lab->nama} untuk {$label} oleh {$oleh->nama}: {$catatan}", $oleh);
        });
    }

    // =====================================================================================
    // Sidik & keaslian LHP
    // =====================================================================================

    /** Sidik seluruh hasil parameter: berubah bila ada nilai yang diubah setelah diperiksa/diparaf. */
    public function sidikHasil(Permohonan $p): string
    {
        $rows = PermohonanParameter::query()->where('permohonan_id', $p->id)->orderBy('id')
            ->get(['id', 'lab_id', 'nama', 'metode', 'persyaratan', 'jumlah', 'hasil', 'memenuhi', 'keterangan'])
            ->map(fn ($r) => [$r->id, $r->lab_id, $r->nama, $r->metode, $r->persyaratan, $r->jumlah, $r->hasil, $r->memenuhi, $r->keterangan])->all();

        return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Sidik isi LHP: SHA-256 atas isi kanonik + HMAC dengan APP_KEY.
     * Hash bisa dicocokkan siapa pun; HMAC membuktikan dokumen dibuat oleh server ini
     * sehingga perubahan langsung di database pun terdeteksi di halaman verifikasi.
     *
     * @return array{0:string,1:string}
     */
    public function sidik(Permohonan $p, Lhu $lhu): array
    {
        if ((int) $lhu->versi_sidik === 3) {
            return $this->sidikSistem($p, $lhu);
        }
        if ((int) $lhu->versi_sidik < 2) {
            // Format LHU sebelum revisi 2: satu hasil per lab.
            $hasil = HasilUji::query()->where('permohonan_id', $p->id)->orderBy('lab_id')
                ->get(['lab_id', 'hasil'])->map(fn ($h) => [$h->lab_id, $h->hasil])->all();
            $isi = json_encode([
                'nomor' => $lhu->nomor, 'kode' => $p->kode, 'verifikasi' => $lhu->kode_verifikasi,
                'sampel' => $p->nama_sampel, 'komoditas' => $p->jenis_komoditas, 'jumlah' => $p->jumlah_sampel,
                'tujuan' => $p->tujuan->value, 'status' => $lhu->status_izin->value, 'hasil' => $hasil,
                'validated_at' => $lhu->validated_at?->format('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_UNICODE);
        } else {
            $isi = [
                'v' => 2, 'nomor' => $lhu->nomor, 'kode' => $p->kode, 'kode_sampel' => $p->kode_sampel,
                'verifikasi' => $lhu->kode_verifikasi, 'sampel' => $p->nama_sampel, 'komoditas' => $p->jenis_komoditas,
                'jumlah' => $p->jumlah_sampel, 'tujuan' => $p->tujuan->value, 'status' => $lhu->status_izin->value,
                'hasil' => $this->sidikHasil($p), 'oleh' => $lhu->validated_by,
                'validated_at' => $lhu->validated_at?->format('Y-m-d H:i:s'),
            ];
            if ((int) $lhu->versi_sidik >= 4) {
                // Revisi 3: produk (kode & nama per halaman LHP) dan parameter milik tiap produk ikut disidik.
                $isi['v'] = 4;
                $isi['produk'] = PermohonanProduk::query()->where('permohonan_id', $p->id)->orderBy('urut')->get()
                    ->map(fn ($x) => [$x->id, $x->kode, $x->nama, $x->jumlah,
                        PermohonanParameter::query()->where('produk_id', $x->id)->orderBy('id')->pluck('id')->all()])->all();
            }
            $isi = json_encode($isi, JSON_UNESCAPED_UNICODE);
        }
        $hash = hash('sha256', $isi);

        return [$hash, hash_hmac('sha256', $hash, (string) config('app.key'))];
    }

    /**
     * Sidik LHP yang diterbitkan versi sebelumnya (branch "sistem": satu LHP per sampel, tabel sampel & hasil_parameter).
     * Dihitung persis seperti saat diterbitkan agar dokumen lama tetap terverifikasi asli.
     *
     * @return array{0:string,1:string}
     */
    private function sidikSistem(Permohonan $p, Lhu $lhu): array
    {
        $sampel = DB::table('sampel')->where('permohonan_id', $p->id)->orderBy('urut')->get()
            ->map(fn ($s) => [$s->kode, $s->jenis_sampel, DB::table('hasil_parameter')->where('sampel_id', $s->id)->orderBy('urutan')->orderBy('id')->get()
                ->map(fn ($h) => [$h->bidang, $h->nama, json_decode((string) $h->ulangan, true), $h->satuan, $h->persyaratan, $h->metode, $h->keterangan])->all()])->all();
        $raw = DB::table('permohonan')->where('id', $p->id)->first();
        $isi = [
            'nomor' => $lhu->nomor, 'kode' => $p->kode, 'kode_sampel' => $p->kode_sampel, 'verifikasi' => $lhu->kode_verifikasi,
            'pelanggan' => $raw->pelanggan ?? null, 'alamat' => $p->alamat, 'layanan' => $p->jenis_bayar?->value,
            'diterima' => $p->diterima_at?->format('Y-m-d'), 'sampel' => $sampel,
            'ttd' => [$lhu->getAttributes()['ttd_nama'] ?? null, $lhu->getAttributes()['ttd_nip'] ?? null, $lhu->getAttributes()['ttd_jabatan'] ?? null],
            'validated_at' => $lhu->validated_at?->format('Y-m-d H:i:s'),
        ];
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

    // =====================================================================================
    // Otorisasi objek & berkas
    // =====================================================================================

    /** Siapa boleh melihat permohonan (otorisasi tingkat objek, dipakai Policy). */
    public function bolehLihat(User $u, Permohonan $p): bool
    {
        if ($u->isStaff()) {
            return true;
        }
        if ($u->role === Role::PenggunaJasa) {
            return $u->sePerusahaanDengan($p->user_id);
        }

        // Analis & penyelia: hanya sampel yang pernah/sedang diuji di lab-nya.
        return $u->lab_id !== null && TahapLab::query()->where('permohonan_id', $p->id)->where('lab_id', $u->lab_id)->exists();
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
