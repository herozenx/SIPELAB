<?php

use App\Support\ParameterBawaan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi 2 (catatan rapat):
 * - peran baru: Operator, Operator Bendahara, Penyelia, Penanggung Jawab Lab (kolom role sudah string)
 * - parameter uji + jumlah pengujian per permohonan; hasil diinput per parameter dan masuk LHP
 * - registrasi sampel (kode sampel SP.M/SP.PE/SP.S + DDMMYY.NN) dan surat tugas per bidang
 * - pemeriksaan Admin Lab, paraf Penyelia, pengesahan Penanggung Jawab (jejak persetujuan)
 * - pengembalian ke lab: revisi data vs uji ulang
 * - nomor LHP urut per tahun (agenda)
 * Data lama dikonversi: hasil per lab menjadi baris parameter "Pengujian Lab ...".
 */
return new class extends Migration
{
    /** Database yang sudah memakai migrasi branch "sistem" (versi sebelumnya yang sempat live di Vercel). */
    private bool $dariSistem = false;

    public function up(): void
    {
        $this->dariSistem = Schema::hasTable('sampel') && Schema::hasTable('hasil_parameter');
        if ($this->dariSistem && Schema::hasTable('parameter_uji') && ! Schema::hasTable('parameter_uji_sistem')) {
            // Katalog parameter versi "sistem" berbeda kolomnya: disimpan sebagai arsip, tabel baru dibuat ulang.
            $this->arsipkanTabel('parameter_uji', 'parameter_uji_sistem');
        }

        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'nip')) {
                $t->string('nip', 30)->nullable()->after('telepon');
            }
            if (! Schema::hasColumn('users', 'alamat')) {
                $t->string('alamat', 300)->nullable()->after('instansi');
            }
        });

        // Kolom yang sudah dibuat versi "sistem" (kode_sampel, alamat, diterima_at) dipakai ulang.
        $kolom = fn (string $c) => ! Schema::hasColumn('permohonan', $c);
        Schema::table('permohonan', function (Blueprint $t) use ($kolom) {
            if ($kolom('kode_sampel')) {
                $t->string('kode_sampel', 25)->nullable()->unique()->after('kode');
            }
            if ($kolom('alamat')) {
                $t->string('alamat', 300)->nullable()->after('asal_sampel');
            }
            foreach (['bentuk' => 20, 'kemasan' => 20, 'suhu' => 10, 'berat' => 20, 'nomor_aju' => 60, 'nomor_pnbp' => 60, 'bank_bayar' => 60, 'channel_bayar' => 40, 'ntb' => 40] as $c => $n) {
                if ($kolom($c)) {
                    $t->string($c, $n)->nullable();
                }
            }
            foreach (['diterima_at', 'billing_at', 'billing_kedaluwarsa_at', 'dibayar_at'] as $c) {
                if ($kolom($c)) {
                    $t->timestamp($c)->nullable();
                }
            }
            if ($kolom('diterima_oleh')) {
                $t->foreignId('diterima_oleh')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        Schema::create('parameter_uji', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 10)->unique();
            $t->string('nama', 120);
            $t->string('bidang', 40);
            $t->foreignId('lab_id')->constrained('labs');
            $t->string('metode', 150);
            $t->string('persyaratan', 150)->nullable();
            $t->string('satuan', 30)->nullable();
            $t->string('keterangan', 300)->nullable(); // bawaan kolom Keterangan LHP, mis. LoD/LoQ
            $t->unsignedInteger('tarif')->default(0);
            $t->boolean('akreditasi')->default(true);
            $t->boolean('aktif')->default(true);
            $t->unsignedSmallInteger('urutan')->default(0);
            $t->timestamps();
        });

        Schema::create('permohonan_parameter', function (Blueprint $t) {
            $t->id();
            $t->foreignId('permohonan_id')->constrained('permohonan')->cascadeOnDelete();
            $t->foreignId('parameter_id')->nullable()->constrained('parameter_uji')->nullOnDelete();
            $t->foreignId('lab_id')->constrained('labs');
            // Salinan data parameter saat diajukan, supaya LHP tidak berubah bila master parameter diubah.
            $t->string('kode', 10)->nullable();
            $t->string('nama', 120);
            $t->string('bidang', 40);
            $t->string('metode', 150);
            $t->string('persyaratan', 150)->nullable();
            $t->string('satuan', 30)->nullable();
            $t->boolean('akreditasi')->default(true);
            $t->unsignedSmallInteger('jumlah')->default(1);
            $t->unsignedInteger('tarif')->default(0);
            // Hasil pengujian.
            $t->string('hasil', 150)->nullable();
            $t->boolean('memenuhi')->nullable();
            $t->string('keterangan', 300)->nullable();
            $t->foreignId('diinput_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diinput_at')->nullable();
            $t->timestamps();
            $t->index(['permohonan_id', 'lab_id']);
        });

        Schema::create('surat_tugas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('permohonan_id')->constrained('permohonan')->cascadeOnDelete();
            $t->foreignId('lab_id')->constrained('labs');
            // Satu surat tugas per sampel (No. FPPC dari kode sampel); satu baris per bidang untuk penyelianya.
            $t->string('nomor', 80)->index();
            $t->foreignId('penyelia_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('dibuat_oleh')->constrained('users');
            $t->text('catatan')->nullable();
            $t->timestamps();
            $t->unique(['permohonan_id', 'lab_id']);
        });

        Schema::create('persetujuan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('permohonan_id')->constrained('permohonan')->cascadeOnDelete();
            $t->string('jenis', 10); // PERIKSA | PARAF | SAH
            $t->foreignId('lab_id')->nullable()->constrained('labs');
            $t->foreignId('user_id')->constrained('users');
            $t->string('catatan', 500)->nullable();
            // Sidik hasil uji saat diparaf: paraf tidak berlaku lagi bila hasil berubah sesudahnya.
            $t->char('sidik', 64);
            $t->timestamp('created_at')->nullable();
            $t->index(['permohonan_id', 'jenis']);
        });

        Schema::create('pengembalian', function (Blueprint $t) {
            $t->id();
            $t->foreignId('permohonan_id')->constrained('permohonan')->cascadeOnDelete();
            $t->foreignId('lab_id')->nullable()->constrained('labs'); // kosong = perbaikan data oleh Administrator
            $t->string('jenis', 10); // REVISI | UJI_ULANG | PERBAIKAN
            $t->string('catatan', 1000);
            $t->foreignId('oleh')->constrained('users');
            $t->string('dari_status', 30);
            $t->text('data_lama')->nullable();
            $t->timestamp('selesai_at')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['permohonan_id', 'lab_id']);
        });

        $adaTahun = Schema::hasColumn('lhu', 'tahun');
        Schema::table('lhu', function (Blueprint $t) use ($adaTahun) {
            if (! $adaTahun) {
                $t->unsignedSmallInteger('tahun')->nullable();
            }
            $t->unsignedInteger('nomor_urut')->nullable();
            $t->unsignedTinyInteger('versi_sidik')->default(1);
            $t->unique(['tahun', 'nomor_urut']);
        });

        $this->konversiData();
    }

    private function konversiData(): void
    {
        $now = now();
        $labLama = DB::table('labs')->where('aktif', true)->orderBy('urutan')->get();

        // Lab/bidang pengujian sesuai LHPS Balai; lab yang sudah ada dibiarkan.
        $urutan = (int) DB::table('labs')->max('urutan');
        foreach (ParameterBawaan::LAB as [$nama, , $norma]) {
            if (! DB::table('labs')->where('nama', $nama)->exists()) {
                DB::table('labs')->insert(['nama' => $nama, 'urutan' => ++$urutan, 'tarif' => 0, 'norma_hari' => $norma, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $labId = DB::table('labs')->pluck('id', 'nama');
        foreach (ParameterBawaan::daftar() as $i => $p) {
            DB::table('parameter_uji')->insert([
                'kode' => $p['kode'], 'nama' => $p['nama'], 'bidang' => $p['bidang'], 'lab_id' => $labId[$p['lab']],
                'metode' => $p['metode'], 'persyaratan' => $p['persyaratan'], 'satuan' => $p['satuan'], 'keterangan' => $p['keterangan'],
                'tarif' => $p['tarif'], 'akreditasi' => $p['akreditasi'], 'aktif' => true, 'urutan' => $i + 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Status lama (termasuk "cek administrasi" dari versi sistem) -> validasi hasil oleh Administrator.
        foreach (['permohonan', 'status_log'] as $tabel) {
            DB::table($tabel)->whereIn('status', ['MENUNGGU_VALIDASI', 'MENUNGGU_CEK_ADM'])->update(['status' => 'MENUNGGU_PEMERIKSAAN']);
        }
        // Peran Admin/Administrasi versi sistem = Administrator (superadmin) di versi ini.
        DB::table('users')->whereIn('role', ['administrasi', 'admin'])->update(['role' => 'superadmin']);
        DB::table('lhu')->whereNull('nomor')->delete();

        // LHP versi sistem: nomor urut agenda sudah ada; sidiknya dihitung dengan cara versi sistem (versi 3).
        if (Schema::hasColumn('lhu', 'urut')) {
            DB::table('lhu')->whereNotNull('nomor')->whereNotNull('urut')->update(['nomor_urut' => DB::raw('urut'), 'versi_sidik' => 3]);
        }
        // LHU lama: isi tahun & nomor urut dari nomornya ("0001/.../2026") bila tidak bentrok.
        foreach (DB::table('lhu')->whereNotNull('nomor')->whereNull('nomor_urut')->get(['id', 'nomor', 'validated_at']) as $l) {
            $tahun = preg_match('#/(\d{4})$#', $l->nomor, $m) ? (int) $m[1] : (int) substr((string) $l->validated_at, 0, 4);
            $bentrok = DB::table('lhu')->where('tahun', $tahun)->where('nomor_urut', (int) $l->nomor)->exists();
            DB::table('lhu')->where('id', $l->id)->update(['tahun' => $tahun, 'nomor_urut' => $bentrok ? null : (int) $l->nomor]);
        }

        // Hasil per parameter versi sistem (per sampel A/B/C) -> baris parameter permohonan.
        $hasilSistem = $this->dariSistem
            ? DB::table('hasil_parameter')->join('sampel', 'sampel.id', '=', 'hasil_parameter.sampel_id')
                ->orderBy('sampel.urut')->orderBy('hasil_parameter.urutan')->orderBy('hasil_parameter.id')
                ->get(['hasil_parameter.*', 'sampel.permohonan_id', 'sampel.kode as kode_sampel_item', 'sampel.urut as sampel_urut'])->groupBy('permohonan_id')
            : collect();
        foreach ($hasilSistem as $pid => $rows) {
            $banyakSampel = $rows->pluck('sampel_id')->unique()->count() > 1;
            foreach ($rows as $h) {
                DB::table('permohonan_parameter')->insert([
                    'permohonan_id' => $pid, 'lab_id' => $h->lab_id,
                    'nama' => mb_substr(str_replace('_', '', $h->nama).($banyakSampel && $h->kode_sampel_item ? ' ('.$h->kode_sampel_item.')' : ''), 0, 120),
                    'bidang' => $h->bidang, 'metode' => $h->metode ?: '-', 'persyaratan' => $h->persyaratan, 'satuan' => $h->satuan,
                    'jumlah' => 1, 'tarif' => 0,
                    'hasil' => mb_substr(implode('; ', (array) json_decode((string) $h->ulangan, true)), 0, 150) ?: null,
                    'memenuhi' => ! $h->melebihi, 'keterangan' => $h->keterangan ? mb_substr($h->keterangan, 0, 300) : null,
                    'diinput_oleh' => $h->petugas_id, 'diinput_at' => $h->updated_at,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // Permohonan lama (tanpa parameter): satu baris "Pengujian Lab X" per lab lama.
        $hasilLama = DB::table('hasil_uji')->get()->groupBy('permohonan_id');
        foreach (DB::table('permohonan')->where('status', '!=', 'DITOLAK')->get() as $p) {
            $hasil = $hasilLama->get($p->id, collect())->keyBy('lab_id');
            $labSistem = $hasilSistem->get($p->id, collect())->pluck('lab_id')->unique();
            foreach ($labLama as $lab) {
                if ($labSistem->contains($lab->id)) {
                    continue; // sudah dari hasil per parameter versi sistem
                }
                $h = $hasil->get($lab->id);
                if (! $h && in_array($p->status, ['SELESAI', 'MENUNGGU_PEMERIKSAAN'], true)) {
                    continue; // lab itu tidak menguji permohonan ini
                }
                DB::table('permohonan_parameter')->insert([
                    'permohonan_id' => $p->id, 'lab_id' => $lab->id, 'nama' => 'Pengujian Lab '.$lab->nama,
                    'bidang' => $lab->nama, 'metode' => '-', 'jumlah' => $p->jumlah_sampel, 'tarif' => $lab->tarif,
                    'hasil' => $h ? ($h->hasil === 'POSITIF' ? 'Positif' : 'Negatif') : null,
                    'memenuhi' => $h ? $h->hasil !== 'POSITIF' : null, 'keterangan' => $h?->catatan ? mb_substr($h->catatan, 0, 300) : null,
                    'diinput_oleh' => $h?->petugas_id, 'diinput_at' => $h?->created_at,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                // Sampel yang sedang di lab: semua lab berikutnya diuji paralel mulai sekarang.
                if ($p->status === 'DI_LAB' && ! $h && ! DB::table('tahap_lab')->where('permohonan_id', $p->id)->where('lab_id', $lab->id)->exists()) {
                    DB::table('tahap_lab')->insert([
                        'permohonan_id' => $p->id, 'lab_id' => $lab->id, 'norma_hari' => $lab->norma_hari,
                        'masuk_at' => $now, 'batas_at' => $now->copy()->addDays(max(1, $lab->norma_hari)),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
        DB::table('permohonan')->update(['current_lab_id' => null]);
    }

    /**
     * Ganti nama tabel beserta sequence & indeksnya (PostgreSQL menyimpan nama indeks/sequence secara global),
     * supaya tabel baru bernama sama bisa dibuat.
     */
    private function arsipkanTabel(string $lama, string $baru): void
    {
        Schema::rename($lama, $baru);
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        $seq = DB::selectOne('SELECT pg_get_serial_sequence(?, ?) AS s', [$baru, 'id'])?->s;
        if ($seq) {
            DB::statement('ALTER SEQUENCE '.$seq.' RENAME TO '.$baru.'_id_seq');
        }
        foreach (DB::select('SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ?', [$baru]) as $i) {
            if (str_starts_with($i->indexname, $lama.'_')) {
                DB::statement('ALTER INDEX "'.$i->indexname.'" RENAME TO "'.$baru.substr($i->indexname, strlen($lama)).'"');
            }
        }
    }

    public function down(): void
    {
        Schema::table('lhu', function (Blueprint $t) {
            $t->dropUnique(['tahun', 'nomor_urut']);
            $t->dropColumn(['tahun', 'nomor_urut', 'versi_sidik']);
        });
        Schema::dropIfExists('pengembalian');
        Schema::dropIfExists('persetujuan');
        Schema::dropIfExists('surat_tugas');
        Schema::dropIfExists('permohonan_parameter');
        Schema::dropIfExists('parameter_uji');
        Schema::table('permohonan', function (Blueprint $t) {
            $t->dropConstrainedForeignId('diterima_oleh');
            $t->dropUnique(['kode_sampel']);
            $t->dropColumn(['kode_sampel', 'alamat', 'bentuk', 'kemasan', 'suhu', 'berat', 'diterima_at', 'billing_at', 'billing_kedaluwarsa_at', 'nomor_aju', 'nomor_pnbp', 'dibayar_at', 'bank_bayar', 'channel_bayar', 'ntb']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['nip', 'alamat']));
    }
};
