<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rapat 19 Nov: paraf penyelia digambar lalu tampil di LHP; jabatan penanda tangan dipilih saat pengesahan.
        if (! Schema::hasColumn('persetujuan', 'paraf_gambar')) {
            Schema::table('persetujuan', fn (Blueprint $t) => $t->text('paraf_gambar')->nullable());
        }
        if (! Schema::hasColumn('lhu', 'jabatan_penanda')) {
            Schema::table('lhu', fn (Blueprint $t) => $t->string('jabatan_penanda', 20)->nullable());
        }

        // Administrator dipisah lagi dari Super Admin: akun administrasi yang sempat digabung dikembalikan perannya.
        DB::table('users')->where('email', 'administrasi@lab.local')->where('role', 'superadmin')->update(['role' => 'administrasi']);

        // Air/Es proses UPI "E. coli & Coliform": satu parameter, Rp160.000 per contoh, sesuai daftar tarif.
        DB::table('parameter_uji')->where('kode', 'CM')->where('nama', 'Coliform (membran filter)')
            ->update(['nama' => 'Escherichia coli & Coliform (Air/Es proses UPI)']);
        DB::table('parameter_uji')->where('kode', 'CM')->whereIn('tarif', [50000, 80000])->update(['tarif' => 160000]);
        // ECM dinonaktifkan (bukan dihapus) agar permohonan lama yang memakainya tetap utuh.
        DB::table('parameter_uji')->where('kode', 'ECM')->update(['aktif' => false]);
    }

    public function down(): void
    {
        Schema::table('persetujuan', fn (Blueprint $t) => $t->dropColumn('paraf_gambar'));
        Schema::table('lhu', fn (Blueprint $t) => $t->dropColumn('jabatan_penanda'));
    }
};
