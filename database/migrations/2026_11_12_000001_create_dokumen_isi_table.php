<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Isi berkas unggahan (base64) untuk hosting tanpa disk permanen, mis. Vercel. Dipakai bila SIPELAB_BERKAS_DB=true. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_isi', function (Blueprint $t) {
            $t->foreignId('dokumen_id')->primary()->constrained('dokumen')->cascadeOnDelete();
            $t->longText('isi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_isi');
    }
};
