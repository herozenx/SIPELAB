<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sensory test diisi per sub-item seperti LHPS Organoleptik (form operator):
 * 1. Keadaan Beku, 2. Thawing, 3. Keadaan Segar, 4. Produk Kering, 5. Produk Kaleng.
 * - parameter_uji.sub_item  : daftar kelompok & item (JSON)
 * - hasil_parameter.rincian : nilai per item yang diisi operator (JSON), untuk diedit ulang bila dikembalikan
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parameter_uji', function (Blueprint $t) {
            $t->text('sub_item')->nullable();
        });
        Schema::table('hasil_parameter', function (Blueprint $t) {
            $t->text('rincian')->nullable();
        });
        DB::table('parameter_uji')->where('kelompok', 'O')->where('kode', 'O')
            ->update(['sub_item' => json_encode(\App\Models\ParameterUji::SUB_ITEM_ORGANOLEPTIK, JSON_UNESCAPED_UNICODE), 'pilihan' => json_encode(['7,0', '8,0', '9,0'])]);
    }

    public function down(): void
    {
        Schema::table('hasil_parameter', fn (Blueprint $t) => $t->dropColumn('rincian'));
        Schema::table('parameter_uji', fn (Blueprint $t) => $t->dropColumn('sub_item'));
    }
};
