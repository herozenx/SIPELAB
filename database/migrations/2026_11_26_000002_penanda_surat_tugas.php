<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Zoom 26 Nov: penanda tangan surat tugas dipilih saat diterbitkan (ada beberapa nama); disimpan per surat.
        if (! Schema::hasColumn('surat_tugas', 'penanda_nama')) {
            Schema::table('surat_tugas', function (Blueprint $t) {
                $t->string('penanda_nama', 150)->nullable();
                $t->string('penanda_nip', 30)->nullable();
            });
        }

        // Kop/jabatan "Plt. ..." diganti; nilai yang sudah diubah Administrator dibiarkan.
        DB::table('settings')->where('key', 'jabatan_kepala_lab')
            ->whereIn('value', ['Plt. Kepala Laboratorium Penguji', 'Kepala Laboratorium Penguji'])
            ->update(['value' => 'Kepala/Penanggung Jawab Laboratorium Pengujian']);
        Cache::forget('sipelab.settings');
    }

    public function down(): void
    {
        Schema::table('surat_tugas', fn (Blueprint $t) => $t->dropColumn(['penanda_nama', 'penanda_nip']));
    }
};
