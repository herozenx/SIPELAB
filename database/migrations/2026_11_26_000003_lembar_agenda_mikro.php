<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lembar kerja Agenda Pemeriksaan mikrobiologi (formulir L/OK/SUB II/8.6c kiriman Balai):
 * isian pengamatan per sampel (media, pengenceran, uji biokimia) disimpan per parameter.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('permohonan_parameter', 'lembar_kerja')) {
            Schema::table('permohonan_parameter', function (Blueprint $t) {
                $t->json('lembar_kerja')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('permohonan_parameter', 'lembar_kerja')) {
            Schema::table('permohonan_parameter', fn (Blueprint $t) => $t->dropColumn('lembar_kerja'));
        }
    }
};
