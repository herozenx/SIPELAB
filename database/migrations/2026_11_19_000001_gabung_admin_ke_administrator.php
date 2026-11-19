<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Peran "Admin" (verifikasi & konfirmasi bayar) digabung ke Administrasi, yang kini tampil sebagai "Administrator".
 * Akun ber-peran 'admin' dipindah ke 'administrasi' agar tetap bisa masuk.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'admin')->update(['role' => 'administrasi']);
    }

    public function down(): void
    {
        // Tidak bisa dibedakan lagi mana yang dulu 'admin'.
    }
};
