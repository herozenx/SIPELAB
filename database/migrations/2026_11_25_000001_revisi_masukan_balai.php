<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi 4 (masukan Balai 25 Nov 2026):
 * - akun perusahaan: satu admin perusahaan + beberapa operator (induk_id), NIK operator
 * - komoditas & berat sampel per produk
 * - persetujuan pemohon (metode, biaya, waktu) dan kaji ulang permintaan saat verifikasi
 * - disposisi: penyelia menugaskan analis per parameter
 * - nama & tarif parameter sesuai daftar tarif PNBP resmi (PMK 1/2025)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('induk_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('nik', 16)->nullable();
        });

        Schema::table('permohonan_produk', function (Blueprint $t) {
            $t->string('komoditas', 80)->nullable();
            $t->string('berat', 30)->nullable();
        });

        Schema::table('permohonan', function (Blueprint $t) {
            $t->timestamp('pemohon_setuju_at')->nullable();
            $t->json('kaji_ulang')->nullable();
            // Jenis layanan baru (TEST_RESULT, UJI_PROFISIENSI, ...) lebih dari 10 karakter.
            $t->string('jenis_bayar', 20)->default('MANDIRI')->change();
            // Asal sampel tidak lagi diisi di form pengajuan; alamat diambil dari akun.
            $t->string('asal_sampel', 150)->nullable()->change();
        });
        Schema::table('users', fn (Blueprint $t) => $t->string('tipe_pengguna', 20)->nullable()->change());

        Schema::table('permohonan_parameter', function (Blueprint $t) {
            $t->foreignId('analis_id')->nullable()->constrained('users')->nullOnDelete();
        });

        // Rapat 19 Nov: paraf penyelia digambar lalu tampil di LHP; jabatan penanda tangan dipilih saat pengesahan.
        Schema::table('persetujuan', fn (Blueprint $t) => $t->text('paraf_gambar')->nullable());
        Schema::table('lhu', fn (Blueprint $t) => $t->string('jabatan_penanda', 20)->nullable());

        // Komoditas lama (satu per permohonan) disalin ke produknya.
        foreach (DB::table('permohonan')->whereNotNull('jenis_komoditas')->get(['id', 'jenis_komoditas']) as $p) {
            DB::table('permohonan_produk')->where('permohonan_id', $p->id)->whereNull('komoditas')
                ->update(['komoditas' => mb_substr((string) $p->jenis_komoditas, 0, 80)]);
        }

        // Administrator dipisah lagi dari Super Admin: akun administrasi yang sempat digabung dikembalikan perannya.
        DB::table('users')->where('email', 'administrasi@lab.local')->where('role', 'superadmin')->update(['role' => 'administrasi']);

        // Hanya nilai bawaan lama yang diganti; yang sudah diubah Administrator dibiarkan.
        DB::table('parameter_uji')->where('kode', 'C')->where('nama', 'Coliform')->update(['nama' => 'Total Coliform']);
        DB::table('parameter_uji')->where('kode', 'CK')->where('tarif', 166000)->update(['tarif' => 150000]);
        // Air/Es proses UPI "E. coli & Coliform": satu parameter, Rp160.000 per contoh, sesuai daftar tarif.
        DB::table('parameter_uji')->where('kode', 'CM')->where('nama', 'Coliform (membran filter)')
            ->update(['nama' => 'Escherichia coli & Coliform (Air/Es proses UPI)']);
        DB::table('parameter_uji')->where('kode', 'CM')->where('tarif', 50000)->update(['tarif' => 160000]);
        // ECM dinonaktifkan (bukan dihapus) agar permohonan lama yang memakainya tetap utuh.
        DB::table('parameter_uji')->where('kode', 'ECM')->update(['aktif' => false]);
    }

    public function down(): void
    {
        Schema::table('permohonan_parameter', fn (Blueprint $t) => $t->dropConstrainedForeignId('analis_id'));
        Schema::table('persetujuan', fn (Blueprint $t) => $t->dropColumn('paraf_gambar'));
        Schema::table('lhu', fn (Blueprint $t) => $t->dropColumn('jabatan_penanda'));
        Schema::table('permohonan', fn (Blueprint $t) => $t->dropColumn(['pemohon_setuju_at', 'kaji_ulang']));
        Schema::table('permohonan_produk', fn (Blueprint $t) => $t->dropColumn(['komoditas', 'berat']));
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('induk_id');
            $t->dropColumn('nik');
        });
    }
};
