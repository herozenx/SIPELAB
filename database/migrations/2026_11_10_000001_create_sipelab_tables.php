<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labs', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 60)->unique();
            $t->unsignedTinyInteger('urutan');
            $t->unsignedInteger('tarif')->default(0);
            $t->boolean('aktif')->default(true);
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 100);
            $t->string('email', 120)->unique();
            $t->string('password');
            $t->string('role', 20)->index();
            $t->foreignId('lab_id')->nullable()->constrained('labs')->nullOnDelete();
            $t->string('instansi', 150)->nullable();
            $t->string('telepon', 20)->nullable();
            $t->boolean('aktif')->default(true);
            $t->boolean('wajib_ganti_password')->default(false);
            $t->unsignedTinyInteger('failed_attempts')->default(0);
            $t->timestamp('locked_until')->nullable();
            $t->unsignedInteger('auth_version')->default(0);
            $t->timestamp('last_login_at')->nullable();
            $t->string('last_login_ip', 45)->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key', 40)->primary();
            $t->string('value', 300);
        });

        Schema::create('permohonan', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 20)->nullable()->unique();
            $t->foreignId('user_id')->constrained('users');
            $t->string('nama_sampel', 120);
            $t->string('jenis_komoditas', 80);
            $t->unsignedSmallInteger('jumlah_sampel');
            $t->string('asal_sampel', 150);
            $t->string('tujuan', 10);
            $t->text('keterangan')->nullable();
            $t->string('status', 30)->index();
            $t->foreignId('current_lab_id')->nullable()->constrained('labs')->nullOnDelete();
            $t->unsignedBigInteger('tagihan')->nullable();
            $t->string('kode_billing', 15)->nullable()->unique();
            $t->string('ntpn', 16)->nullable()->unique();
            $t->string('alasan_tolak', 500)->nullable();
            $t->timestamps();
            $t->index(['user_id', 'status']);
            $t->index(['status', 'current_lab_id']);
        });

        Schema::create('hasil_uji', function (Blueprint $t) {
            $t->id();
            $t->foreignId('permohonan_id')->constrained('permohonan')->cascadeOnDelete();
            $t->foreignId('lab_id')->constrained('labs');
            $t->foreignId('petugas_id')->constrained('users');
            $t->string('hasil', 10);
            $t->text('catatan')->nullable();
            $t->timestamps();
            $t->unique(['permohonan_id', 'lab_id']);
        });

        Schema::create('dokumen', function (Blueprint $t) {
            $t->id();
            $t->foreignId('permohonan_id')->constrained('permohonan')->cascadeOnDelete();
            $t->string('jenis', 20);
            $t->foreignId('lab_id')->nullable()->constrained('labs')->nullOnDelete();
            $t->string('stored_name', 80)->unique();
            $t->string('original_name', 150);
            $t->string('mime', 50);
            $t->unsignedInteger('size');
            $t->char('sha256', 64);
            $t->foreignId('uploaded_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('lhu', function (Blueprint $t) {
            $t->id();
            $t->foreignId('permohonan_id')->unique()->constrained('permohonan')->cascadeOnDelete();
            $t->string('nomor', 60)->nullable()->unique();
            $t->string('status_izin', 12);
            $t->char('kode_verifikasi', 12)->nullable()->unique();
            $t->char('hash', 64)->nullable();
            $t->char('signature', 64)->nullable();
            $t->foreignId('validated_by')->nullable()->constrained('users');
            $t->timestamp('validated_at')->nullable();
            $t->timestamps();
        });

        Schema::create('status_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('permohonan_id')->constrained('permohonan')->cascadeOnDelete();
            $t->string('status', 30);
            $t->string('keterangan', 500)->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('audit_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 40)->index();
            $t->string('entity', 30)->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->text('detail')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        foreach (['audit_log', 'status_log', 'lhu', 'dokumen', 'hasil_uji', 'permohonan', 'settings', 'sessions', 'users', 'labs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
