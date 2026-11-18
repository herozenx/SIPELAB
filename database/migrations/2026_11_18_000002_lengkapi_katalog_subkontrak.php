<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil uji coba dengan Buku Sampel Oktober:
 * - AOZ dipakai 9 sampel: persyaratan/metode/keterangan diambil dari LHP asli (Hamparan Segara Artha, Kudatama Mas, Marinal Indoprima).
 * - Kolom EKSTERNAL Buku Sampel (Angler, LPPMHP, Saraswati) dipakai: kolom subkontrak diperlebar untuk beberapa lab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permohonan', function (Blueprint $t) {
            $t->string('subkontrak', 60)->nullable()->change();
        });

        DB::table('parameter_uji')->where('kelompok', 'KIMIA')->where('kode', 'AOZ')
            ->where(fn ($q) => $q->whereNull('metode')->orWhere('persyaratan', '[]'))
            ->update(['persyaratan' => json_encode(['RPA 0,5 µg/kg'], JSON_UNESCAPED_UNICODE), 'metode' => 'IKM/OK/LP/SUB II/12', 'keterangan' => 'CCβ 0,25 µg/kg']);
    }

    public function down(): void
    {
        // Data katalog tidak dikembalikan (bisa sudah diedit Superadmin).
    }
};
