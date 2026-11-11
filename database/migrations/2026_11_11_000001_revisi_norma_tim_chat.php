<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi 1:
 * - norma waktu pengujian per lab (hari kerja) + tahapan lab per permohonan
 * - jenis pengguna jasa: MANDIRI (bayar di awal) / EKSTERNAL (bayar saat LHU jadi)
 * - operator yang menangani sampel per lab
 * - chat langsung pengguna jasa <-> admin/operator + status online
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labs', function (Blueprint $t) {
            $t->unsignedTinyInteger('norma_hari')->default(2)->after('tarif');
        });
        // Pembagian awal 6 hari kerja: Nekropsi 1, Mikrobiologi 3, Kimia 2 (bisa diubah di menu Lab & tarif).
        foreach (['Nekropsi' => 1, 'Mikrobiologi' => 3, 'Kimia' => 2] as $nama => $hari) {
            DB::table('labs')->where('nama', $nama)->update(['norma_hari' => $hari]);
        }

        Schema::table('users', function (Blueprint $t) {
            $t->string('tipe_pengguna', 10)->nullable()->after('instansi');
            $t->timestamp('last_seen_at')->nullable()->index();
        });
        DB::table('users')->where('role', 'pengguna_jasa')->update(['tipe_pengguna' => 'MANDIRI']);

        Schema::table('permohonan', function (Blueprint $t) {
            $t->string('jenis_bayar', 10)->default('MANDIRI')->after('tujuan');
            $t->timestamp('masuk_lab_at')->nullable();
            $t->timestamp('target_selesai_at')->nullable();
        });

        // Daftar hari libur bisa lebih dari 300 karakter.
        Schema::table('settings', function (Blueprint $t) {
            $t->text('value')->change();
        });

        Schema::create('tahap_lab', function (Blueprint $t) {
            $t->id();
            $t->foreignId('permohonan_id')->constrained('permohonan')->cascadeOnDelete();
            $t->foreignId('lab_id')->constrained('labs');
            $t->unsignedTinyInteger('norma_hari');
            $t->timestamp('masuk_at');
            $t->timestamp('batas_at');
            $t->timestamp('selesai_at')->nullable();
            $t->timestamps();
            $t->unique(['permohonan_id', 'lab_id']);
            $t->index(['lab_id', 'selesai_at']);
        });

        Schema::create('tahap_operator', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tahap_lab_id')->constrained('tahap_lab')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users');
            $t->timestamp('created_at')->nullable();
            $t->unique(['tahap_lab_id', 'user_id']);
        });

        Schema::create('chat_threads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->timestamp('last_message_at')->nullable()->index();
            $t->unsignedBigInteger('staff_read_id')->default(0);
            $t->unsignedBigInteger('user_read_id')->default(0);
            $t->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('thread_id')->constrained('chat_threads')->cascadeOnDelete();
            $t->foreignId('sender_id')->constrained('users');
            $t->text('body');
            $t->timestamp('created_at')->nullable();
            $t->index(['thread_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_threads');
        Schema::dropIfExists('tahap_operator');
        Schema::dropIfExists('tahap_lab');
        Schema::table('permohonan', fn (Blueprint $t) => $t->dropColumn(['jenis_bayar', 'masuk_lab_at', 'target_selesai_at']));
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['last_seen_at']);
            $t->dropColumn(['tipe_pengguna', 'last_seen_at']);
        });
        Schema::table('labs', fn (Blueprint $t) => $t->dropColumn('norma_hari'));
    }
};
