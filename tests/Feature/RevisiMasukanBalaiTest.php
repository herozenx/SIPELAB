<?php

namespace Tests\Feature;

use App\Enums\JenisBayar;
use App\Enums\Status;
use App\Enums\Tujuan;
use App\Models\ParameterUji;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\AlurUji;
use Tests\TestCase;

/** Revisi 4: masukan Balai 25 Nov 2026 (akun perusahaan, tujuan permohonan, kaji ulang, disposisi, tarif). */
class RevisiMasukanBalaiTest extends TestCase
{
    use AlurUji, RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['sipelab.berkas_di_database' => true]);
    }

    private function setuju(): array
    {
        return ['metode' => 1, 'biaya' => 1, 'waktu' => 1];
    }

    public function test_daftar_dengan_alamat_dan_operator_lalu_satu_perusahaan_saling_lihat(): void
    {
        $this->get('/daftar')->assertOk()->assertSee('name="alamat"', false)->assertSee('Tambah operator');
        $this->post('/daftar', [
            'nama' => 'Budi Santoso', 'instansi' => 'PT Laut Jaya', 'alamat' => 'Jl. Pelabuhan 5, Surabaya',
            'email' => 'budi@lautjaya.test', 'telepon_lokal' => '81234567890',
            'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123',
            'operator' => [
                ['nama' => 'Siti Operator', 'nik' => '3578010101900001', 'email' => 'siti@lautjaya.test', 'telepon' => '0812 1111 2222', 'password' => 'Operator123'],
                ['nama' => '', 'nik' => '', 'email' => '', 'password' => ''], // blok kosong diabaikan
            ],
        ])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

        $admin = User::query()->where('email', 'budi@lautjaya.test')->firstOrFail();
        $op = User::query()->where('email', 'siti@lautjaya.test')->firstOrFail();
        $this->assertTrue($admin->adminPerusahaan());
        $this->assertSame($admin->id, $op->induk_id);
        $this->assertSame('PT Laut Jaya', $op->instansi);
        $this->assertSame('Jl. Pelabuhan 5, Surabaya', $op->alamat);
        $this->assertSame('+6281211112222', $op->telepon);
        auth()->logout();

        // Operator mengajukan; admin perusahaan ikut melihat, perusahaan lain tidak.
        $p = $this->ajukan('siti@lautjaya.test', ['H']);
        $this->assertSame('Jl. Pelabuhan 5, Surabaya', $p->alamat); // alamat dari akun, bukan dari form
        $this->as('budi@lautjaya.test')->get("/permohonan/{$p->id}")->assertOk();
        $this->as('budi@lautjaya.test')->get('/permohonan')->assertOk()->assertSee($p->kode_sampel);
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}")->assertNotFound();

        // Admin perusahaan menambah & menonaktifkan operator; operator nonaktif tidak bisa masuk.
        $this->as('budi@lautjaya.test')->get('/akun')->assertOk()->assertSee('Operator perusahaan')->assertSee('Siti Operator');
        $this->as('budi@lautjaya.test')->post('/akun/operator', ['op' => ['nama' => 'Andi', 'nik' => '123', 'email' => 'andi@lautjaya.test', 'password' => 'Operator123']])
            ->assertSessionHasErrors('op.nik');
        $this->as('budi@lautjaya.test')->post('/akun/operator', ['op' => ['nama' => 'Andi', 'nik' => '3578010101900002', 'email' => 'andi@lautjaya.test', 'password' => 'Operator123']])
            ->assertSessionHas('success');
        $andi = User::query()->where('email', 'andi@lautjaya.test')->firstOrFail();
        $this->as('siti@lautjaya.test')->post("/akun/operator/{$andi->id}")->assertNotFound(); // operator tidak bisa mengatur operator
        $this->as('budi@lautjaya.test')->post("/akun/operator/{$andi->id}")->assertSessionHas('success');
        $this->assertFalse($andi->fresh()->aktif);
        auth()->logout();
        $this->post('/masuk', ['email' => 'andi@lautjaya.test', 'password' => 'Operator123'])->assertSessionHasErrors('email');
    }

    public function test_daftar_menolak_email_operator_ganda(): void
    {
        $this->post('/daftar', [
            'nama' => 'Budi Santoso', 'instansi' => 'PT Laut Jaya', 'alamat' => 'Jl. Pelabuhan 5',
            'email' => 'budi@lautjaya.test', 'telepon_lokal' => '81234567890', 'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123',
            'operator' => [['nama' => 'Siti', 'nik' => '3578010101900001', 'email' => 'budi@lautjaya.test', 'password' => 'Operator123']],
        ])->assertSessionHasErrors('operator');
        $this->post('/daftar', [
            'nama' => 'Budi Santoso', 'instansi' => 'PT Laut Jaya', 'email' => 'budi@lautjaya.test', 'telepon_lokal' => '81234567890',
            'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123',
        ])->assertSessionHasErrors('alamat');
        $this->assertSame(0, User::query()->where('email', 'budi@lautjaya.test')->count());
    }

    public function test_tujuan_ekspor_langsung_berkode_sp_e_dan_form_baru(): void
    {
        Carbon::setTestNow('2026-11-25 10:00:00');
        $this->as('pengguna@lab.local')->get('/permohonan/baru')->assertOk()
            ->assertSee('Tujuan permohonan')->assertSee('Ekspor (Test Result)')->assertSee('Uji Banding')
            ->assertSee('name="produk[0][komoditas]"', false)->assertSee('name="produk[0][berat]"', false)
            ->assertSee('Pemohon menyetujui')->assertDontSee('name="asal_sampel"', false)->assertDontSee('Tujuan distribusi');

        // Tanpa persetujuan & tanpa berat ditolak.
        $this->as('pengguna@lab.local')->post('/permohonan', [
            'jenis_layanan' => 'TEST_RESULT',
            'produk' => [['komoditas' => 'Krustasea', 'nama' => 'Udang Beku', 'jumlah' => 1, 'parameter' => $this->idParameter('H')]],
        ])->assertSessionHasErrors(['setuju', 'produk.0.berat']);
        // Petugas-only tidak bisa dipilih pengguna jasa.
        $this->as('pengguna@lab.local')->post('/permohonan', [
            'jenis_layanan' => 'SURVEILEN', 'setuju' => $this->setuju(),
            'produk' => [['komoditas' => 'Krustasea', 'nama' => 'Udang Beku', 'jumlah' => 1, 'berat' => '1 kg', 'parameter' => $this->idParameter('H')]],
        ])->assertSessionHasErrors('jenis_layanan');

        $this->as('pengguna@lab.local')->post('/permohonan', [
            'jenis_layanan' => 'TEST_RESULT', 'setuju' => $this->setuju(),
            'produk' => [['komoditas' => 'Krustasea', 'nama' => 'Udang Beku', 'jumlah' => 500, 'berat' => '2 kg', 'parameter' => $this->idParameter('H', 'C')]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $p = Permohonan::query()->latest('id')->firstOrFail();
        $this->assertSame('SP.E/251126.01', $p->kode_sampel);
        $this->assertSame('SP.E/251126.01', $p->produk()->value('kode')); // satu produk: tanpa akhiran
        $this->assertSame(JenisBayar::TestResult, $p->jenis_bayar);
        $this->assertSame(Tujuan::Ekspor, $p->tujuan);
        $this->assertNotNull($p->pemohon_setuju_at);
        $this->assertSame([1, 1], $p->parameter()->pluck('jumlah')->all()); // tidak ikut jumlah sampel 500
        $this->assertSame('Total Coliform', $p->parameter()->where('kode', 'C')->value('nama'));
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('SP.E/251126.01')->assertSee('2 kg');
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}/bukti-terima.pdf")->assertNotFound(); // belum diregistrasi

        // Registrasi di hari yang sama: kode tetap; Ekspor dibayar di awal.
        $this->verifikasi($p, 'TEST_RESULT');
        $p->refresh();
        $this->assertSame('SP.E/251126.01', $p->kode_sampel);
        $this->assertSame(Status::MenungguBilling, $p->status);
        $this->assertSame('dapat', $p->kaji_ulang['kesimpulan']);

        // Permohonan kedua diganti petugas ke Monitoring: nomor urut sama, awalan berubah; dibayar di belakang.
        $q = $this->ajukan();
        $this->assertSame('SP.M/251126.02', $q->kode_sampel);
        $this->verifikasi($q, 'MONITORING');
        $q->refresh();
        $this->assertSame('SP.MN/251126.02', $q->kode_sampel);
        $this->assertSame(Status::SiapUji, $q->status);

        // Ditolak: kode SP dilepas dan dipakai permohonan berikutnya.
        $r = $this->ajukan();
        $this->assertSame('SP.M/251126.03', $r->kode_sampel);
        $this->as('admin@lab.local')->post("/permohonan/{$r->id}/verifikasi", ['kesimpulan' => 'tidak', 'alasan' => 'Sampel tidak memenuhi syarat'])->assertSessionHas('success');
        $this->assertNull($r->fresh()->kode_sampel);
        $this->assertSame(Status::Ditolak, $r->fresh()->status);
        $this->assertSame('SP.M/251126.03', $this->ajukan()->kode_sampel);
    }

    public function test_disposisi_penyelia_menugaskan_analis_per_parameter(): void
    {
        Carbon::setTestNow('2026-11-25 10:00:00');
        $p = $this->ajukan('pengguna@lab.local', ['A', 'E1', 'H']);
        $this->verifikasi($p);
        $this->bayarLunas($p);
        $this->suratTugas($p);

        $mikro = $this->u('mikro@lab.local');
        $dewi = $this->u('mikro2@lab.local');
        [$alt, $ec] = [$p->parameter()->where('kode', 'A')->value('id'), $p->parameter()->where('kode', 'E1')->value('id')];
        $histamin = $p->parameter()->where('kode', 'H')->value('id');

        $this->as('penyelia.mikro@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('Disposisi analis')->assertSee('Dewi Mikrobiologi');
        $this->as('mikro@lab.local')->post("/permohonan/{$p->id}/disposisi", ['analis' => [$alt => $mikro->id]])->assertSessionHas('error');
        $this->as('penyelia.mikro@lab.local')->post("/permohonan/{$p->id}/disposisi", ['analis' => [$alt => $this->u('kimia@lab.local')->id]])
            ->assertSessionHas('error'); // analis lab lain ditolak
        $this->as('penyelia.mikro@lab.local')->post("/permohonan/{$p->id}/disposisi", ['analis' => [$alt => $mikro->id, $ec => $dewi->id, $histamin => $mikro->id]])
            ->assertSessionHas('success');
        $this->assertSame($mikro->id, $p->parameter()->whereKey($alt)->value('analis_id'));
        $this->assertSame($dewi->id, $p->parameter()->whereKey($ec)->value('analis_id'));
        $this->assertNull($p->parameter()->whereKey($histamin)->value('analis_id')); // parameter lab lain tidak tersentuh

        // Analis hanya bisa mengisi parameter yang ditugaskan kepadanya.
        $this->as('mikro@lab.local')->post("/permohonan/{$p->id}/hasil", ['aksi' => 'simpan', 'hasil' => [
            $alt => ['hasil' => '1,0 x 10²', 'memenuhi' => '1'], $ec => ['hasil' => 'DIUBAH', 'memenuhi' => '0'],
        ]])->assertSessionHas('success');
        $this->assertSame('1,0 x 10²', $p->parameter()->whereKey($alt)->value('hasil'));
        $this->assertNull($p->parameter()->whereKey($ec)->value('hasil'));
        $this->as('mikro2@lab.local')->post("/permohonan/{$p->id}/hasil", ['aksi' => 'selesai', 'hasil' => [$ec => ['hasil' => 'Negatif', 'memenuhi' => '1']]])
            ->assertSessionHas('success');
        $this->assertSame($dewi->id, $p->parameter()->whereKey($ec)->value('diinput_oleh')); // paraf analis

        $this->as('penyelia.mikro@lab.local')->get('/disposisi.pdf?tanggal=2026-11-25')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->as('pengguna@lab.local')->get('/disposisi.pdf')->assertForbidden();
    }

    public function test_tarif_dan_nama_parameter_sesuai_daftar_resmi(): void
    {
        $this->assertSame(150000, ParameterUji::query()->where('kode', 'CK')->value('tarif'));
        $this->assertSame('Total Coliform', ParameterUji::query()->where('kode', 'C')->value('nama'));
        $this->assertSame(80000, ParameterUji::query()->where('kode', 'CM')->value('tarif'));
        $this->assertSame(80000, ParameterUji::query()->where('kode', 'ECM')->value('tarif'));
    }
}
