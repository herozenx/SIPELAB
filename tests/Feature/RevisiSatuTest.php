<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ChatThread;
use App\Models\Lab;
use App\Models\Setting;
use App\Models\TahapLab;
use App\Models\User;
use App\Support\HariKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\AlurUji;
use Tests\TestCase;

/** Fitur umum: norma waktu, tim lab, penanganan, chat, halaman per peran, panduan. */
class RevisiSatuTest extends TestCase
{
    use AlurUji, RefreshDatabase;

    protected bool $seed = true;

    private const SETELAN = [
        'nama_instansi' => 'Balai Uji', 'alamat_instansi' => 'Sidoarjo', 'biaya_admin' => 10000, 'norma_total_hari' => 6,
        'kota_lhp' => 'Sidoarjo', 'kode_formulir_lhp' => 'FL/OK/LP/SUB II/8.6.d',
        'format_nomor_lhp' => '{urut}/LAB/BPPMHKP.SBY.II/KI.310/{bulan}/{tahun}', 'format_nomor_surat_tugas' => '{kode_sampel}/FPPCU/SUB II/{bulan}/{tahun}',
        'kota_surat_tugas' => 'Surabaya', 'kode_formulir_surat_tugas' => 'FL/OK/SUB II/8.5.3.d', 'jabatan_kepala_lab' => 'Plt. Kepala Laboratorium Penguji',
    ];

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

    public function test_tahap_lab_tercatat_dengan_batas_norma_dan_analis(): void
    {
        $p = $this->ajukan();
        $this->verifikasi($p);
        $this->bayarLunas($p);
        $this->suratTugas($p);

        $p->refresh();
        $this->assertNotNull($p->masuk_lab_at);
        $this->assertNotNull($p->target_selesai_at);
        $mikro = TahapLab::query()->where('permohonan_id', $p->id)->whereHas('lab', fn ($q) => $q->where('nama', 'Mikrobiologi'))->firstOrFail();
        $this->assertSame(3, $mikro->norma_hari);
        $this->assertTrue($mikro->batas_at->gt($mikro->masuk_at));

        // Analis menangani; analis kedua di tim Mikrobiologi ikut menangani.
        $this->as('mikro2@lab.local')->post("/permohonan/{$p->id}/tangani")->assertSessionHas('success');
        $this->as('mikro2@lab.local')->post("/permohonan/{$p->id}/tangani")->assertSessionHas('error'); // tidak dobel
        $this->ujiLab($p, 'mikro@lab.local');
        $this->assertNotNull($mikro->fresh()->selesai_at);
        $this->assertEqualsCanonicalizing(['Dewi Mikrobiologi', 'Analis Mikrobiologi'], $mikro->fresh()->operator->pluck('nama')->all());

        // Nama analis & tahapan lab hanya untuk petugas Balai; pengguna jasa cukup melihat status.
        $this->as('admin@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('Tim penanganan')->assertSee('Dewi Mikrobiologi');
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}")->assertOk()
            ->assertSee('Sedang diproses/pengujian')->assertDontSee('Dewi Mikrobiologi')
            ->assertDontSee('Tim penanganan')->assertDontSee('Lab Kimia');
    }

    public function test_analis_lab_lain_tidak_bisa_menangani(): void
    {
        $p = $this->ajukan('pengguna@lab.local', ['H']);
        $this->verifikasi($p, 'EKSTERNAL');
        $this->suratTugas($p);
        $this->as('mikro@lab.local')->post("/permohonan/{$p->id}/tangani")->assertNotFound();
        $this->as('eksternal@lab.local')->post("/permohonan/{$p->id}/tangani")->assertNotFound();
        $this->as('pengguna@lab.local')->post("/permohonan/{$p->id}/tangani")->assertSessionHas('error'); // pemilik, bukan petugas lab
        $this->as('kimia@lab.local')->post("/permohonan/{$p->id}/tangani")->assertSessionHas('success');
    }

    // ---------------------------------------------------------------- Tim lab

    public function test_admin_lab_menambah_analis_dan_penyelia_dan_hanya_bisa_mengubah_tim_lab(): void
    {
        $lab = Lab::query()->where('nama', 'Kimia')->value('id');
        $this->as('admin@lab.local')->get('/tim')->assertOk()->assertSee('Tim Lab Kimia')->assertSee('Penyelia Kimia');
        $this->as('admin@lab.local')->post('/tim', ['nama' => 'Analis Baru', 'email' => 'baru@lab.local', 'lab_id' => $lab, 'role' => 'petugas_lab'])
            ->assertRedirect('/tim')->assertSessionHas('sandi_sementara');
        $this->as('admin@lab.local')->post('/tim', ['nama' => 'Penyelia Baru', 'email' => 'py@lab.local', 'lab_id' => $lab, 'role' => 'penyelia', 'nip' => '19800101 200501 1 001'])
            ->assertRedirect('/tim');
        $this->as('admin@lab.local')->post('/tim', ['nama' => 'Coba', 'email' => 'coba@lab.local', 'lab_id' => $lab, 'role' => 'superadmin'])->assertSessionHasErrors('role');

        $baru = $this->u('baru@lab.local');
        $this->assertSame(Role::PetugasLab, $baru->role);
        $this->assertSame($lab, $baru->lab_id);
        $this->assertTrue($baru->wajib_ganti_password);
        $this->assertSame(Role::Penyelia, $this->u('py@lab.local')->role);

        // Tidak bisa dipakai untuk mengubah akun di luar tim lab.
        $super = $this->u('superadmin@lab.local');
        $this->as('admin@lab.local')->put("/tim/{$super->id}", ['nama' => 'X', 'email' => 'x@x.id', 'lab_id' => $lab, 'role' => 'petugas_lab', 'aktif' => 1])->assertNotFound();
        $this->as('admin@lab.local')->post("/tim/{$super->id}/reset")->assertNotFound();
        $this->as('admin@lab.local')->post('/tim/'.$this->u('bendahara@lab.local')->id.'/reset')->assertNotFound();

        $this->as('mikro@lab.local')->get('/tim')->assertForbidden();
        $this->as('operator@lab.local')->get('/tim')->assertForbidden();
        $this->as('pengguna@lab.local')->get('/tim')->assertForbidden();
    }

    public function test_administrator_membuat_akun_peran_baru(): void
    {
        $this->as('superadmin@lab.local')->post('/admin/pengguna', [
            'nama' => 'Bendahara Dua', 'email' => 'bdh2@lab.local', 'role' => 'bendahara', 'password' => 'Sandi1234', 'aktif' => 1,
        ])->assertRedirect('/admin/pengguna');
        $this->assertSame(Role::Bendahara, $this->u('bdh2@lab.local')->role);
        $this->assertNull($this->u('bdh2@lab.local')->lab_id);

        $this->as('superadmin@lab.local')->post('/admin/pengguna', [
            'nama' => 'Penyelia Tanpa Lab', 'email' => 'x2@lab.local', 'role' => 'penyelia', 'password' => 'Sandi1234',
        ])->assertSessionHasErrors('lab_id');

        // Login cukup email & sandi; peran dikenali otomatis.
        $this->post('/masuk', ['email' => 'pj@lab.local', 'password' => 'Lab12345!'])->assertRedirect('/beranda');
    }

    // ---------------------------------------------------------------- Chat

    public function test_chat_pengguna_dibalas_analis_dan_status_belum_dibaca(): void
    {
        $this->as('pengguna@lab.local')->get('/chat')->assertOk();
        $thread = ChatThread::query()->where('user_id', $this->u('pengguna@lab.local')->id)->firstOrFail();

        $this->as('pengguna@lab.local')->postJson("/api/chat/{$thread->id}", ['isi' => 'Halo, sampel saya kapan selesai?'])
            ->assertCreated()->assertJsonPath('pesan.saya', true);
        $this->as('pengguna@lab.local')->postJson("/api/chat/{$thread->id}", ['isi' => '   '])->assertStatus(422);

        $this->as('admin@lab.local')->getJson('/api/chat/status')->assertJsonPath('belum', 1);
        $this->as('mikro@lab.local')->getJson('/api/chat/status')->assertJsonPath('belum', 1);

        $this->as('mikro@lab.local')->getJson("/api/chat/{$thread->id}")->assertOk()->assertJsonPath('pesan.0.isi', 'Halo, sampel saya kapan selesai?');
        $this->as('admin@lab.local')->getJson('/api/chat/status')->assertJsonPath('belum', 0);
        $this->as('mikro@lab.local')->postJson("/api/chat/{$thread->id}", ['isi' => 'Sedang diuji, besok selesai.'])->assertCreated();

        $this->as('pengguna@lab.local')->getJson('/api/chat/status')->assertJsonPath('belum', 1);
        $this->as('pengguna@lab.local')->getJson("/api/chat/{$thread->id}?setelah=0")->assertOk()
            ->assertJsonPath('pesan.1.nama', 'Analis Mikrobiologi')
            ->assertJsonPath('pesan.1.jabatan', 'Analis/Laboran Mikrobiologi')
            ->assertJsonPath('pesan.1.balai', true);

        $this->as('eksternal@lab.local')->getJson("/api/chat/{$thread->id}")->assertNotFound();
        $this->as('eksternal@lab.local')->postJson("/api/chat/{$thread->id}", ['isi' => 'iseng'])->assertNotFound();
        $this->as('admin@lab.local')->get('/chat')->assertOk()->assertSee('Rini Hapsari');
    }

    public function test_daftar_petugas_online_dari_aktivitas_terakhir(): void
    {
        User::query()->where('email', 'kimia@lab.local')->update(['last_seen_at' => now()]);
        User::query()->where('email', 'mikro@lab.local')->update(['last_seen_at' => now()->subMinutes(10)]);
        $thread = ChatThread::query()->create(['user_id' => $this->u('pengguna@lab.local')->id]);

        $online = collect($this->as('pengguna@lab.local')->getJson("/api/chat/{$thread->id}")->json('online'))->pluck('nama');
        $this->assertContains('Analis Kimia', $online);
        $this->assertNotContains('Analis Mikrobiologi', $online);
        $this->assertNotContains('Rini Hapsari', $online);
    }

    // ---------------------------------------------------------------- Halaman tampil tanpa error

    public function test_semua_halaman_utama_tampil_untuk_tiap_peran(): void
    {
        $this->get('/daftar')->assertOk()->assertDontSee('Jenis pengguna jasa');
        $this->get('/')->assertOk()->assertSee('Panduan')->assertSee('Cek keaslian LHP')->assertSee('Organoleptik');
        $this->get('/masuk')->assertOk()->assertDontSee('Masuk sebagai');

        $p = $this->sampaiSelesai();
        $s = $this->ajukan('eksternal@lab.local', ['CS']);

        foreach (['superadmin', 'admin', 'operator', 'bendahara', 'pj', 'penyelia.kimia', 'kimia', 'pengguna'] as $akun) {
            $email = "{$akun}@lab.local";
            $this->as($email)->get('/beranda')->assertOk();
            $this->as($email)->get('/permohonan')->assertOk();
            $this->as($email)->get('/chat')->assertOk();
        }
        foreach (['superadmin', 'admin', 'operator', 'bendahara', 'pj'] as $akun) {
            $this->as("{$akun}@lab.local")->get("/permohonan/{$p->id}")->assertOk()->assertSee($p->kode_sampel);
            $this->as("{$akun}@lab.local")->get("/permohonan/{$s->id}")->assertOk();
            $this->as("{$akun}@lab.local")->get('/laporan')->assertOk();
        }
        $this->as('kimia@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('Hasil lab Anda');
        $this->as('kimia@lab.local')->get('/riwayat-uji')->assertOk();
        $this->as('penyelia.kimia@lab.local')->get('/riwayat-uji')->assertOk();
        $this->as('superadmin@lab.local')->get('/admin/lab')->assertOk()->assertSee('Norma (hari kerja)');
        $this->as('superadmin@lab.local')->get('/admin/parameter')->assertOk()->assertSee('Histamin')->assertSee('belum punya tarif');
        $this->as('superadmin@lab.local')->get('/admin/parameter?edit='.$p->parameter()->value('parameter_id'))->assertOk()->assertSee('Ubah parameter');
        $this->as('superadmin@lab.local')->get('/admin/pengaturan')->assertOk()->assertSee('Hari libur nasional')->assertSee('0001/LAB/BPPMHKP.SBY.II/KI.310');
        $this->as('superadmin@lab.local')->get('/admin/pengguna?edit='.$this->u('pj@lab.local')->id)->assertOk()->assertSee('Penanggung Jawab Lab');
        $this->as('superadmin@lab.local')->get('/tim')->assertOk();

        // Simpan parameter & tarif baru.
        $h = \App\Models\ParameterUji::query()->where('kode', 'H')->firstOrFail();
        $this->as('superadmin@lab.local')->put("/admin/parameter/{$h->id}", $h->only(['kode', 'nama', 'bidang', 'lab_id', 'metode', 'persyaratan', 'satuan', 'urutan']) + ['tarif' => '150.000', 'aktif' => 1, 'akreditasi' => 1])
            ->assertSessionHas('success');
        $this->assertSame(150000, $h->fresh()->tarif);

        // Simpan pengaturan (format salah ditolak).
        $this->as('superadmin@lab.local')->put('/admin/pengaturan', self::SETELAN + ['hari_libur' => '25-12-2026'])->assertSessionHasErrors('hari_libur');
        $this->as('superadmin@lab.local')->put('/admin/pengaturan', ['format_nomor_lhp' => 'LHP/X'] + self::SETELAN)->assertSessionHasErrors('format_nomor_lhp');
        $this->as('superadmin@lab.local')->put('/admin/pengaturan', self::SETELAN + ['hari_libur' => "2026-12-25\n2026-12-24"])->assertSessionHas('success');
        $this->assertSame("2026-12-24\n2026-12-25", Setting::get('hari_libur'));
    }

    public function test_halaman_publik_dan_pengguna_tidak_membuka_tahapan_internal_lab(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Contoh tampilan pelacakan')->assertDontSee('Di Lab');
        $this->get('/panduan')->assertOk()->assertDontSee('Tim penanganan')->assertDontSee('Jalur uji');

        $p = $this->ajukan('eksternal@lab.local');
        $this->verifikasi($p, 'EKSTERNAL');
        $this->suratTugas($p);
        $this->ujiLab($p, 'organo@lab.local');
        $this->as('eksternal@lab.local')->get('/beranda');
        $this->as('eksternal@lab.local')->get("/permohonan/{$p->id}")->assertOk()
            ->assertSee('Sampel segera diproses di laboratorium')
            ->assertDontSee('Pengujian Lab Organoleptik selesai')->assertDontSee('Surat tugas pengujian diterbitkan')->assertDontSee('Jalur uji');
        $this->as('admin@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('Pengujian Lab Organoleptik selesai');
    }

    // ---------------------------------------------------------------- Panduan

    public function test_panduan_bisa_dibuka_tamu_dan_pengguna(): void
    {
        $this->get('/panduan')->assertOk()
            ->assertSee('Panduan Penggunaan SILANTRO')->assertSee('Daftar bagian')->assertSee('Unduh PDF')
            ->assertSee('Mandiri')->assertSee('Pengujian Eksternal')->assertSee('6 hari kerja')->assertSee('Nomor NTPN tidak perlu diketik')
            ->assertSee('Bagian 09 dari 9')
            ->assertDontSee('wa.me');
        $this->as('pengguna@lab.local')->get('/panduan')->assertOk()->assertSee('Chat petugas');
    }

    public function test_whatsapp_center_tampil_setelah_diisi_di_pengaturan(): void
    {
        $this->get('/')->assertOk()->assertSee('Pusat bantuan')->assertDontSee('wa.me');

        $this->as('superadmin@lab.local')->put('/admin/pengaturan', self::SETELAN + ['whatsapp_center' => 'bukan-nomor'])->assertSessionHasErrors('whatsapp_center');
        $this->as('superadmin@lab.local')->put('/admin/pengaturan', self::SETELAN + ['whatsapp_center' => '0853-8522-0631'])->assertSessionHas('success');

        $this->assertStringStartsWith('https://wa.me/6285385220631', wa_link());
        $this->as('pengguna@lab.local')->get('/chat')->assertOk()->assertSee('https://wa.me/6285385220631', false);
        $this->as('pengguna@lab.local')->get('/panduan')->assertOk()->assertSee('Chat WhatsApp Center');
    }
}
