<?php

namespace Tests\Feature;

use App\Enums\JenisBayar;
use App\Enums\Role;
use App\Enums\Status;
use App\Models\ChatThread;
use App\Models\Lhu;
use App\Models\Permohonan;
use App\Models\Setting;
use App\Models\TahapLab;
use App\Models\User;
use App\Support\HariKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Revisi 1: norma waktu, Mandiri/Eksternal, tim operator, penanganan, panduan, chat. */
class RevisiSatuTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        HariKerja::lupakan();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function u(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function as(string $email): static
    {
        $u = $this->u($email);

        return $this->actingAs($u)->withSession(['auth_version' => $u->auth_version, 'login_at' => time()]);
    }

    private function png(): UploadedFile
    {
        $copy = sys_get_temp_dir().'/'.uniqid().'.png';
        file_put_contents($copy, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        return new UploadedFile($copy, 'bukti.png', 'image/png', null, true);
    }

    private function ajukan(string $email): Permohonan
    {
        $this->as($email)->post('/permohonan', [
            'nama_sampel' => 'Udang vaname', 'jenis_komoditas' => 'Krustasea', 'jumlah_sampel' => 1,
            'asal_sampel' => 'Sidoarjo', 'tujuan' => 'DOMESTIK',
        ])->assertRedirect();

        return Permohonan::query()->latest('id')->firstOrFail();
    }

    private function ujiSemuaLab(Permohonan $p): void
    {
        foreach (['nekropsi', 'mikro', 'kimia'] as $l) {
            $this->as("$l@lab.local")->post("/permohonan/{$p->id}/hasil", ['hasil' => 'NEGATIF'])->assertRedirect('/beranda');
        }
    }

    // ---------------------------------------------------------------- Eksternal

    public function test_eksternal_diuji_dulu_bayar_setelah_lhu_jadi_dan_lhu_terkunci_sampai_lunas(): void
    {
        $p = $this->ajukan('eksternal@lab.local');
        $this->assertSame(JenisBayar::Eksternal, $p->jenis_bayar);

        // Verifikasi tanpa kode billing: langsung masuk lab pertama.
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid', 'jenis_bayar' => 'EKSTERNAL'])
            ->assertSessionHas('success');
        $p->refresh();
        $this->assertSame(Status::DiLab, $p->status);
        $this->assertNull($p->kode_billing);
        $this->assertSame(425000 + 10000, $p->tagihan);

        $this->ujiSemuaLab($p);
        $this->assertSame(Status::MenungguValidasi, $p->fresh()->status);

        // Validasi LHU eksternal wajib kode billing pelunasan.
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/validasi-lhu")->assertSessionHasErrors('kode_billing');
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/validasi-lhu", ['kode_billing' => '820260000000009'])->assertSessionHas('success');
        $p->refresh();
        $this->assertSame(Status::MenungguPembayaran, $p->status);
        $this->assertNotNull(Lhu::query()->where('permohonan_id', $p->id)->value('nomor'));

        // Belum lunas: pengguna belum bisa membuka LHU, admin sudah bisa.
        $this->as('eksternal@lab.local')->get("/permohonan/{$p->id}/lhu")->assertNotFound();
        $this->as('eksternal@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('LHU sudah jadi')->assertSee('820260000000009');
        $this->as('admin@lab.local')->get("/permohonan/{$p->id}/lhu")->assertOk();

        $this->as('eksternal@lab.local')->post("/permohonan/{$p->id}/bayar", ['ntpn' => 'EKST1234EFGH5678', 'bukti' => $this->png()])->assertSessionHas('success');
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/konfirmasi-bayar", ['keputusan' => 'terima'])->assertSessionHas('success');

        $this->assertSame(Status::Selesai, $p->fresh()->status);
        $this->as('eksternal@lab.local')->get("/permohonan/{$p->id}/lhu")->assertOk()->assertSee('Lulus uji, bisa beredar');
    }

    public function test_mandiri_tetap_wajib_kode_billing_saat_verifikasi(): void
    {
        $p = $this->ajukan('pengguna@lab.local');
        $this->assertSame(JenisBayar::Mandiri, $p->jenis_bayar);
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid', 'jenis_bayar' => 'MANDIRI'])
            ->assertSessionHasErrors('kode_billing');
        $this->assertSame(Status::Diajukan, $p->fresh()->status);
    }

    public function test_daftar_sebagai_eksternal(): void
    {
        $this->post('/daftar', [
            'nama' => 'Budi Eksternal', 'instansi' => 'PT Uji', 'email' => 'budi@uji.id', 'telepon' => '081234567890',
            'tipe_pengguna' => 'EKSTERNAL', 'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123',
        ])->assertRedirect('/beranda');
        $this->assertSame(JenisBayar::Eksternal, $this->u('budi@uji.id')->tipe_pengguna);
    }

    // ---------------------------------------------------------------- Norma waktu

    public function test_hari_kerja_melewati_akhir_pekan_dan_hari_libur(): void
    {
        $jumat = Carbon::parse('2026-11-13 15:00', 'Asia/Jakarta'); // Jumat
        $this->assertSame('2026-11-16', HariKerja::batas($jumat, 1)->format('Y-m-d')); // Senin
        $this->assertSame('2026-11-23', HariKerja::batas($jumat, 6)->format('Y-m-d'));

        Setting::put(['hari_libur' => "2026-11-16\n2026-11-17"]);
        $this->assertSame('2026-11-18', HariKerja::batas($jumat, 1)->format('Y-m-d'));

        $batas = Carbon::parse('2026-11-18 23:59:59', 'Asia/Jakarta');
        $this->assertSame(['Tepat waktu', 'ok'], HariKerja::status($batas, Carbon::parse('2026-11-18 10:00', 'Asia/Jakarta')));
        $this->assertSame(['Terlambat 2 hari kerja', 'bad'], HariKerja::status($batas, Carbon::parse('2026-11-20 09:00', 'Asia/Jakarta')));
        $this->assertSame(['Batas hari ini', 'wait'], HariKerja::status($batas, null, Carbon::parse('2026-11-18 08:00', 'Asia/Jakarta')));
    }

    public function test_tahap_lab_tercatat_dengan_batas_norma_dan_operator(): void
    {
        $p = $this->ajukan('pengguna@lab.local');
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid', 'kode_billing' => '820260000000001']);
        $this->as('pengguna@lab.local')->post("/permohonan/{$p->id}/bayar", ['ntpn' => 'ABCD1234EFGH0001', 'bukti' => $this->png()]);
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/konfirmasi-bayar", ['keputusan' => 'terima']);

        $p->refresh();
        $this->assertNotNull($p->masuk_lab_at);
        $this->assertNotNull($p->target_selesai_at);
        $tahap = TahapLab::query()->where('permohonan_id', $p->id)->firstOrFail();
        $this->assertSame(1, $tahap->norma_hari); // Nekropsi
        $this->assertTrue($tahap->batas_at->gt($tahap->masuk_at));

        // Operator menangani, lalu operator kedua di tim Mikrobiologi ikut menangani.
        $this->as('nekropsi@lab.local')->post("/permohonan/{$p->id}/tangani")->assertSessionHas('success');
        $this->as('nekropsi@lab.local')->post("/permohonan/{$p->id}/tangani")->assertSessionHas('error'); // tidak dobel
        $this->as('nekropsi@lab.local')->post("/permohonan/{$p->id}/hasil", ['hasil' => 'NEGATIF']);
        $this->assertNotNull($tahap->fresh()->selesai_at);

        $this->as('mikro2@lab.local')->post("/permohonan/{$p->id}/tangani")->assertSessionHas('success');
        $this->as('mikro@lab.local')->post("/permohonan/{$p->id}/hasil", ['hasil' => 'NEGATIF']);
        $mikro = TahapLab::query()->where('permohonan_id', $p->id)->whereHas('lab', fn ($q) => $q->where('nama', 'Mikrobiologi'))->firstOrFail();
        $this->assertEqualsCanonicalizing(['Dewi Mikrobiologi', 'Petugas Mikrobiologi'], $mikro->operator->pluck('nama')->all());

        // Nama operator terlihat oleh pengguna jasa dan admin.
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('Dewi Mikrobiologi')->assertSee('Petugas Nekropsi');
        $this->as('admin@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('Tim penanganan');
    }

    public function test_petugas_lab_lain_tidak_bisa_menangani(): void
    {
        $p = $this->ajukan('eksternal@lab.local');
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid', 'jenis_bayar' => 'EKSTERNAL']);
        $this->as('kimia@lab.local')->post("/permohonan/{$p->id}/tangani")->assertNotFound();
        $this->as('pengguna@lab.local')->post("/permohonan/{$p->id}/tangani")->assertForbidden();
    }

    // ---------------------------------------------------------------- Tim operator

    public function test_admin_lab_menambah_operator_tim_dan_hanya_bisa_mengubah_operator(): void
    {
        $lab = \App\Models\Lab::query()->where('nama', 'Kimia')->value('id');
        $this->as('admin@lab.local')->get('/tim')->assertOk()->assertSee('Tim Lab Kimia');
        $this->as('admin@lab.local')->post('/tim', ['nama' => 'Operator Baru', 'email' => 'baru@lab.local', 'lab_id' => $lab])
            ->assertRedirect('/tim')->assertSessionHas('sandi_sementara');

        $baru = $this->u('baru@lab.local');
        $this->assertSame(Role::PetugasLab, $baru->role);
        $this->assertSame($lab, $baru->lab_id);
        $this->assertTrue($baru->wajib_ganti_password);

        // Tidak bisa dipakai untuk mengubah akun admin/superadmin.
        $super = $this->u('superadmin@lab.local');
        $this->as('admin@lab.local')->put("/tim/{$super->id}", ['nama' => 'X', 'email' => 'x@x.id', 'lab_id' => $lab, 'aktif' => 1])->assertNotFound();
        $this->as('admin@lab.local')->post("/tim/{$super->id}/reset")->assertNotFound();

        $this->as('nekropsi@lab.local')->get('/tim')->assertForbidden();
        $this->as('pengguna@lab.local')->get('/tim')->assertForbidden();
    }

    // ---------------------------------------------------------------- Chat

    public function test_chat_pengguna_dibalas_operator_dan_status_belum_dibaca(): void
    {
        $this->as('pengguna@lab.local')->get('/chat')->assertOk();
        $thread = ChatThread::query()->where('user_id', $this->u('pengguna@lab.local')->id)->firstOrFail();

        $this->as('pengguna@lab.local')->postJson("/api/chat/{$thread->id}", ['isi' => 'Halo, sampel saya kapan selesai?'])
            ->assertCreated()->assertJsonPath('pesan.saya', true);
        $this->as('pengguna@lab.local')->postJson("/api/chat/{$thread->id}", ['isi' => '   '])->assertStatus(422);

        // Admin & operator sama-sama melihat pesan belum dibaca.
        $this->as('admin@lab.local')->getJson('/api/chat/status')->assertJsonPath('belum', 1);
        $this->as('mikro@lab.local')->getJson('/api/chat/status')->assertJsonPath('belum', 1);

        // Operator membuka percakapan => terbaca, lalu membalas dengan nama & jabatannya.
        $this->as('mikro@lab.local')->getJson("/api/chat/{$thread->id}")->assertOk()->assertJsonPath('pesan.0.isi', 'Halo, sampel saya kapan selesai?');
        $this->as('admin@lab.local')->getJson('/api/chat/status')->assertJsonPath('belum', 0);
        $this->as('mikro@lab.local')->postJson("/api/chat/{$thread->id}", ['isi' => 'Sedang diuji, besok selesai.'])->assertCreated();

        $this->as('pengguna@lab.local')->getJson('/api/chat/status')->assertJsonPath('belum', 1);
        $this->as('pengguna@lab.local')->getJson("/api/chat/{$thread->id}?setelah=0")->assertOk()
            ->assertJsonPath('pesan.1.nama', 'Petugas Mikrobiologi')
            ->assertJsonPath('pesan.1.jabatan', 'Petugas Lab Mikrobiologi')
            ->assertJsonPath('pesan.1.balai', true);

        // Pengguna lain tidak bisa membaca/mengirim ke percakapan ini.
        $this->as('eksternal@lab.local')->getJson("/api/chat/{$thread->id}")->assertNotFound();
        $this->as('eksternal@lab.local')->postJson("/api/chat/{$thread->id}", ['isi' => 'iseng'])->assertNotFound();

        // Kotak masuk petugas menampilkan percakapan.
        $this->as('admin@lab.local')->get('/chat')->assertOk()->assertSee('Rini Hapsari');
    }

    public function test_daftar_petugas_online_dari_aktivitas_terakhir(): void
    {
        User::query()->where('email', 'kimia@lab.local')->update(['last_seen_at' => now()]);
        User::query()->where('email', 'mikro@lab.local')->update(['last_seen_at' => now()->subMinutes(10)]);
        $thread = ChatThread::query()->create(['user_id' => $this->u('pengguna@lab.local')->id]);

        $online = collect($this->as('pengguna@lab.local')->getJson("/api/chat/{$thread->id}")->json('online'))->pluck('nama');
        $this->assertContains('Petugas Kimia', $online);
        $this->assertNotContains('Petugas Mikrobiologi', $online);
        $this->assertNotContains('Rini Hapsari', $online); // pengguna jasa tidak masuk daftar petugas
    }

    // ---------------------------------------------------------------- Halaman tampil tanpa error

    public function test_semua_halaman_utama_tampil_untuk_tiap_peran(): void
    {
        // Halaman tamu (sebelum login).
        $this->get('/daftar')->assertOk()->assertSee('Jenis pengguna jasa');
        $this->get('/')->assertOk()->assertSee('Panduan');

        // Satu sampel eksternal di lab yang sudah lewat norma, agar panel "lewat norma" ikut dirender.
        $p = $this->ajukan('eksternal@lab.local');
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid', 'jenis_bayar' => 'EKSTERNAL']);
        TahapLab::query()->where('permohonan_id', $p->id)->update(['batas_at' => now()->subDays(5)]);
        $this->as('nekropsi@lab.local')->post("/permohonan/{$p->id}/tangani");

        $this->as('admin@lab.local')->get('/beranda')->assertOk()->assertSee('Sampel melewati norma waktu')->assertSee('Petugas Nekropsi');
        $this->as('nekropsi@lab.local')->get('/beranda')->assertOk()->assertSee('Lewat')->assertSee('Petugas Nekropsi');
        $this->as('eksternal@lab.local')->get('/beranda')->assertOk()->assertSee('Eksternal')->assertSee('Baca panduan');
        $this->as('eksternal@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('Norma waktu sampel jadi');
        $this->as('admin@lab.local')->get('/permohonan')->assertOk()->assertSee($p->kode);
        $this->as('superadmin@lab.local')->get('/admin/lab')->assertOk()->assertSee('Norma (hari kerja)');
        $this->as('superadmin@lab.local')->get('/admin/pengaturan')->assertOk()->assertSee('Hari libur nasional');
        $this->as('superadmin@lab.local')->get('/admin/pengguna?edit='.$this->u('eksternal@lab.local')->id)->assertOk()->assertSee('Jenis pengguna jasa');
        $this->as('superadmin@lab.local')->get('/tim')->assertOk();
        $this->as('superadmin@lab.local')->get('/laporan')->assertOk();
        $this->as('mikro@lab.local')->get('/chat')->assertOk();

        // Simpan pengaturan norma & hari libur (format salah ditolak).
        $set = ['nama_instansi' => 'Balai Uji', 'alamat_instansi' => 'Sidoarjo', 'biaya_admin' => 10000, 'prefix_lhu' => 'LHU/X', 'norma_total_hari' => 6];
        $this->as('superadmin@lab.local')->put('/admin/pengaturan', $set + ['hari_libur' => '25-12-2026'])->assertSessionHasErrors('hari_libur');
        $this->as('superadmin@lab.local')->put('/admin/pengaturan', $set + ['hari_libur' => "2026-12-25\n2026-12-24"])->assertSessionHas('success');
        $this->assertSame("2026-12-24\n2026-12-25", Setting::get('hari_libur'));
    }

    // ---------------------------------------------------------------- Panduan

    public function test_panduan_bisa_dibuka_tamu_dan_pengguna(): void
    {
        $this->get('/panduan')->assertOk()
            ->assertSee('Panduan Penggunaan SIPELAB')->assertSee('Daftar bagian')->assertSee('Unduh PDF')
            ->assertSee('Mandiri')->assertSee('Eksternal')->assertSee('6 hari kerja')
            ->assertSee('Bagian 10 dari 10')
            ->assertDontSee('wa.me'); // nomor belum diisi => tombol WhatsApp tidak tampil
        $this->as('pengguna@lab.local')->get('/panduan')->assertOk()->assertSee('Chat petugas');
    }

    public function test_whatsapp_center_tampil_setelah_diisi_di_pengaturan(): void
    {
        $this->get('/')->assertOk()->assertSee('Pusat bantuan')->assertDontSee('wa.me');

        $set = ['nama_instansi' => 'Balai Uji', 'alamat_instansi' => 'Sidoarjo', 'biaya_admin' => 10000, 'prefix_lhu' => 'LHU/X', 'norma_total_hari' => 6];
        $this->as('superadmin@lab.local')->put('/admin/pengaturan', $set + ['whatsapp_center' => 'bukan-nomor'])->assertSessionHasErrors('whatsapp_center');
        $this->as('superadmin@lab.local')->put('/admin/pengaturan', $set + ['whatsapp_center' => '0853-8522-0631'])->assertSessionHas('success');

        $this->assertStringStartsWith('https://wa.me/6285385220631', wa_link());
        $this->as('pengguna@lab.local')->get('/chat')->assertOk()->assertSee('https://wa.me/6285385220631', false);
        $this->as('pengguna@lab.local')->get('/panduan')->assertOk()->assertSee('Chat WhatsApp Center');
    }
}
