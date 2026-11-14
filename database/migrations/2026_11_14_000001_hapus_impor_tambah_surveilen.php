<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * - Tujuan "Impor" dihapus (instansi tidak melayani impor): data lama dialihkan ke Domestik.
 * - Jenis pengguna jasa kini dipilih per permohonan: Mandiri / Eksternal / Surveilen
 *   (kolom string yang sudah ada cukup, tidak perlu ubah skema).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permohonan')->where('tujuan', 'IMPOR')->update(['tujuan' => 'DOMESTIK']);
    }

    public function down(): void
    {
        // Tidak dapat dikembalikan: permohonan mana yang dulu Impor tidak disimpan.
    }
};
