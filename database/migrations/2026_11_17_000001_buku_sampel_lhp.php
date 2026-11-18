<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Input operator mengikuti Buku Sampel, LHP Word mengikuti format instansi, peran Administrasi.
 * - sampel           : satu baris per sampel (kode SP.xx/ddmmyy.NN + huruf A/B/C), satu halaman LHP per sampel
 * - parameter_uji    : katalog parameter per lab (kolom Buku Sampel), persyaratan & metode dari LHP asli
 * - hasil_parameter  : centang + hasil per ulangan yang diisi operator (salinan teks agar LHP terbit tidak berubah)
 * - lab              : Mikrobiologi, Kimia, Organoleptik, Radionuklida (Nekropsi dinonaktifkan)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permohonan', function (Blueprint $t) {
            $t->string('jenis_bayar', 20)->default('MANDIRI')->change();
            $t->string('pelanggan', 150)->nullable();
            $t->string('alamat', 300)->nullable();
            $t->string('cp', 60)->nullable();
            $t->string('no_surat', 40)->nullable();
            $t->string('subkontrak', 20)->nullable();
            $t->string('kode_sampel', 30)->nullable()->unique();
            $t->date('kode_tanggal')->nullable();
            $t->unsignedSmallInteger('kode_urut')->nullable();
            $t->timestamp('diterima_at')->nullable();
            $t->boolean('dikembalikan')->default(false);
            $t->unique(['kode_tanggal', 'kode_urut']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('tipe_pengguna', 20)->nullable()->change();
        });

        Schema::create('sampel', function (Blueprint $t) {
            $t->id();
            $t->foreignId('permohonan_id')->constrained('permohonan')->cascadeOnDelete();
            $t->unsignedSmallInteger('urut');
            $t->string('jenis_sampel', 150);
            $t->string('berat', 30)->nullable();
            $t->string('kode', 32)->nullable()->unique();
            $t->timestamps();
            $t->unique(['permohonan_id', 'urut']);
        });

        Schema::create('parameter_uji', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lab_id')->constrained('labs');
            $t->string('kelompok', 30);              // header Buku Sampel: KIMIA, BAKTERIOLOGI, MEMBRAN FILTER, ...
            $t->string('kode', 12);                  // kolom Buku Sampel: F, H, ALT, E, ...
            $t->string('bidang', 40);                // kolom "Bidang Pengujian" di LHP: Biologi, Kimia, ...
            $t->string('bidang_en', 40);             // Biology, Chemical, ...
            $t->string('nama', 200);                 // teks kolom Parameter di LHP; _teks_ = miring (nama spesies)
            $t->string('satuan', 30)->nullable();    // ditambahkan di belakang hasil angka
            $t->text('pilihan')->nullable();         // JSON: tombol hasil cepat, mis. ["Negatif/25 g","Positif/25 g"]
            $t->unsignedTinyInteger('ulangan')->default(1);
            $t->text('persyaratan')->nullable();     // JSON: daftar persyaratan mutu yang biasa dipakai
            $t->string('metode', 150)->nullable();
            $t->string('keterangan', 150)->nullable();
            $t->unsignedSmallInteger('urutan')->default(0);
            $t->boolean('aktif')->default(true);
            $t->timestamps();
            $t->unique(['lab_id', 'kelompok', 'kode']);
        });

        Schema::create('hasil_parameter', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sampel_id')->constrained('sampel')->cascadeOnDelete();
            $t->foreignId('parameter_id')->nullable()->constrained('parameter_uji')->nullOnDelete();
            $t->foreignId('lab_id')->constrained('labs');
            $t->foreignId('petugas_id')->constrained('users');
            $t->text('ulangan');                     // JSON: hasil per ulangan, persis seperti ditulis di LHP
            $t->string('bidang', 40);
            $t->string('bidang_en', 40);
            $t->string('nama', 200);
            $t->string('satuan', 30)->nullable();
            $t->string('persyaratan', 150)->nullable();
            $t->string('metode', 150)->nullable();
            $t->string('keterangan', 150)->nullable();
            $t->boolean('melebihi')->default(false); // catatan internal (rekap LHP melebihi baku mutu), tidak dicetak
            $t->unsignedSmallInteger('urutan')->default(0);
            $t->timestamps();
            $t->unique(['sampel_id', 'parameter_id']);
        });

        Schema::table('lhu', function (Blueprint $t) {
            $t->unsignedSmallInteger('tahun')->nullable();
            $t->unsignedInteger('urut')->nullable();
            $t->foreignId('cek_by')->nullable()->constrained('users');
            $t->timestamp('cek_at')->nullable();
            $t->string('catatan_cek', 500)->nullable();
            $t->string('ttd_nama', 100)->nullable();
            $t->string('ttd_nip', 30)->nullable();
            $t->string('ttd_jabatan', 10)->nullable(); // KEPALA | PJ
            $t->unique(['tahun', 'urut']);
        });

        // Lab mengikuti LHPS instansi. Nekropsi tidak dihapus agar riwayat lama tetap terbaca.
        // Sampel yang sedang di Nekropsi dipindah ke Mikrobiologi (lab pertama) supaya tidak tersangkut.
        $nek = DB::table('labs')->where('nama', 'Nekropsi')->value('id');
        $mik = DB::table('labs')->where('nama', 'Mikrobiologi')->first();
        if ($nek && $mik) {
            $now = now();
            foreach (DB::table('permohonan')->where('status', 'DI_LAB')->where('current_lab_id', $nek)->pluck('id') as $pid) {
                DB::table('permohonan')->where('id', $pid)->update(['current_lab_id' => $mik->id, 'updated_at' => $now]);
                DB::table('tahap_lab')->where('permohonan_id', $pid)->where('lab_id', $nek)->whereNull('selesai_at')->update(['selesai_at' => $now]);
                if (! DB::table('tahap_lab')->where('permohonan_id', $pid)->where('lab_id', $mik->id)->exists()) {
                    DB::table('tahap_lab')->insert(['permohonan_id' => $pid, 'lab_id' => $mik->id, 'norma_hari' => $mik->norma_hari,
                        'masuk_at' => $now, 'batas_at' => \App\Support\HariKerja::batas($now, (int) $mik->norma_hari), 'created_at' => $now, 'updated_at' => $now]);
                }
                DB::table('status_log')->insert(['permohonan_id' => $pid, 'status' => 'DI_LAB', 'created_at' => $now,
                    'keterangan' => 'Lab Nekropsi tidak dipakai lagi (bidang uji mengikuti LHPS). Sampel dipindahkan ke Lab Mikrobiologi']);
            }
        }
        DB::table('labs')->where('nama', 'Nekropsi')->update(['aktif' => false, 'urutan' => 9]);
        DB::table('labs')->where('nama', 'Mikrobiologi')->update(['urutan' => 1]);
        DB::table('labs')->where('nama', 'Kimia')->update(['urutan' => 2]);
        foreach ([['Organoleptik', 3], ['Radionuklida', 4]] as [$nama, $urut]) {
            if (! DB::table('labs')->where('nama', $nama)->exists()) {
                DB::table('labs')->insert(['nama' => $nama, 'urutan' => $urut, 'tarif' => 0, 'norma_hari' => 1, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        // Permohonan lama: satu baris sampel + nama pelanggan dari akun pemohon.
        $rows = DB::table('permohonan')->leftJoin('users', 'users.id', '=', 'permohonan.user_id')
            ->get(['permohonan.id', 'permohonan.nama_sampel', 'permohonan.created_at', 'users.instansi', 'users.nama']);
        foreach ($rows as $r) {
            DB::table('permohonan')->where('id', $r->id)->update(['pelanggan' => $r->instansi ?: $r->nama]);
            DB::table('sampel')->insert(['permohonan_id' => $r->id, 'urut' => 1, 'jenis_sampel' => mb_substr((string) $r->nama_sampel, 0, 150), 'created_at' => $r->created_at, 'updated_at' => $r->created_at]);
        }

        (require database_path('seeders/katalog_parameter.php'))();
    }

    public function down(): void
    {
        Schema::table('lhu', function (Blueprint $t) {
            $t->dropUnique(['tahun', 'urut']);
            $t->dropConstrainedForeignId('cek_by');
            $t->dropColumn(['tahun', 'urut', 'cek_at', 'catatan_cek', 'ttd_nama', 'ttd_nip', 'ttd_jabatan']);
        });
        Schema::dropIfExists('hasil_parameter');
        Schema::dropIfExists('parameter_uji');
        Schema::dropIfExists('sampel');
        Schema::table('permohonan', function (Blueprint $t) {
            $t->dropUnique(['kode_tanggal', 'kode_urut']);
            $t->dropUnique(['kode_sampel']);
            $t->dropColumn(['pelanggan', 'alamat', 'cp', 'no_surat', 'subkontrak', 'kode_sampel', 'kode_tanggal', 'kode_urut', 'diterima_at', 'dikembalikan']);
        });
        DB::table('labs')->where('nama', 'Nekropsi')->update(['aktif' => true, 'urutan' => 1]);
        DB::table('labs')->where('nama', 'Mikrobiologi')->update(['urutan' => 2]);
        DB::table('labs')->where('nama', 'Kimia')->update(['urutan' => 3]);
        DB::table('labs')->whereIn('nama', ['Organoleptik', 'Radionuklida'])->update(['aktif' => false]);
    }
};
