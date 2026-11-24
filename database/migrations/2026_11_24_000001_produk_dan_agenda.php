<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi 3 (dicocokkan dengan buku sampel & LHP Balai 2026):
 * - satu permohonan bisa berisi beberapa produk (satu perusahaan, hari yang sama, beda produk):
 *   kode SP.x/DDMMYY.NN + akhiran A, B, C..., satu nomor agenda dan satu nomor LHP
 * - nomor agenda ("NO. surat" buku sampel) diberikan saat registrasi dan menjadi nomor urut LHP
 * - pengaturan disesuaikan dengan dokumen asli (kota LHP, penanda tangan surat tugas)
 * Permohonan lama dijadikan satu produk agar semua data memakai struktur yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permohonan_produk', function (Blueprint $t) {
            $t->id();
            $t->foreignId('permohonan_id')->constrained('permohonan')->cascadeOnDelete();
            $t->unsignedTinyInteger('urut');
            $t->string('nama', 120);
            $t->unsignedSmallInteger('jumlah')->default(1);
            $t->string('kode', 30)->nullable(); // kode sampel produk, diisi saat registrasi
            $t->timestamps();
            $t->unique(['permohonan_id', 'urut']);
        });

        Schema::table('permohonan_parameter', function (Blueprint $t) {
            $t->foreignId('produk_id')->nullable()->after('permohonan_id')->constrained('permohonan_produk')->cascadeOnDelete();
        });

        Schema::table('permohonan', function (Blueprint $t) {
            $t->unsignedSmallInteger('tahun_agenda')->nullable();
            $t->unsignedInteger('no_agenda')->nullable();
            $t->unique(['tahun_agenda', 'no_agenda']);
        });

        $now = now();
        foreach (DB::table('permohonan')->orderBy('id')->get(['id', 'nama_sampel', 'jumlah_sampel', 'kode_sampel']) as $p) {
            $produk = DB::table('permohonan_produk')->insertGetId([
                'permohonan_id' => $p->id, 'urut' => 1, 'nama' => mb_substr((string) $p->nama_sampel, 0, 120),
                'jumlah' => max(1, (int) $p->jumlah_sampel), 'kode' => $p->kode_sampel, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('permohonan_parameter')->where('permohonan_id', $p->id)->update(['produk_id' => $produk]);
        }

        // Nilai yang masih bawaan lama diganti sesuai dokumen asli; yang sudah diubah Administrator dibiarkan.
        $ganti = [
            'kota_lhp' => ['Sidoarjo', 'Surabaya'],
            'jabatan_kepala_lab' => ['Kepala Laboratorium Penguji', 'Plt. Kepala Laboratorium Penguji'],
            'nama_kepala_lab' => ['', 'Ricat Pahlefi Hidayat, S.St.Pi., M.Si'],
            'nip_kepala_lab' => ['', '19780424 200502 1 001'],
        ];
        foreach ($ganti as $key => [$lama, $baru]) {
            DB::table('settings')->where('key', $key)->where(fn ($q) => $lama === '' ? $q->whereNull('value')->orWhere('value', '') : $q->where('value', $lama))
                ->update(['value' => $baru]);
        }
        cache()->forget('sipelab.settings');
    }

    public function down(): void
    {
        Schema::table('permohonan', function (Blueprint $t) {
            $t->dropUnique(['tahun_agenda', 'no_agenda']);
            $t->dropColumn(['tahun_agenda', 'no_agenda']);
        });
        Schema::table('permohonan_parameter', fn (Blueprint $t) => $t->dropConstrainedForeignId('produk_id'));
        Schema::dropIfExists('permohonan_produk');
    }
};
