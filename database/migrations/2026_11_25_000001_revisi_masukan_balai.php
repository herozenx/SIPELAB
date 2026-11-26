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

        // Komoditas lama (satu per permohonan) disalin ke produknya.
        foreach (DB::table('permohonan')->whereNotNull('jenis_komoditas')->get(['id', 'jenis_komoditas']) as $p) {
            DB::table('permohonan_produk')->where('permohonan_id', $p->id)->whereNull('komoditas')
                ->update(['komoditas' => mb_substr((string) $p->jenis_komoditas, 0, 80)]);
        }

        // Hanya nilai bawaan lama yang diganti; yang sudah diubah Administrator dibiarkan.
        DB::table('parameter_uji')->where('kode', 'C')->where('nama', 'Coliform')->update(['nama' => 'Total Coliform']);
        DB::table('parameter_uji')->where('kode', 'CK')->where('tarif', 166000)->update(['tarif' => 150000]);
        // Air/Es proses UPI "E. coli & Coliform" Rp160.000 per contoh, dibagi rata ke CM dan ECM.
        DB::table('parameter_uji')->whereIn('kode', ['CM', 'ECM'])->where('tarif', 50000)->update(['tarif' => 80000]);
    }

    public function down(): void
    {
        Schema::table('permohonan_parameter', fn (Blueprint $t) => $t->dropConstrainedForeignId('analis_id'));
        Schema::table('permohonan', fn (Blueprint $t) => $t->dropColumn(['pemohon_setuju_at', 'kaji_ulang']));
        Schema::table('permohonan_produk', fn (Blueprint $t) => $t->dropColumn(['komoditas', 'berat']));
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('induk_id');
            $t->dropColumn('nik');
        });
    }
};
