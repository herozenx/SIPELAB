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

    public function test_administrator_terpisah_dari_super_admin(): void
    {
        $this->assertSame('Super Admin', \App\Enums\Role::Superadmin->label());
        $this->assertSame('Administrator', \App\Enums\Role::Administrasi->label());

        $p = $this->ajukan();
        $this->as('administrasi@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['kesimpulan' => 'dapat', 'kaji' => ['personel' => 1, 'bahan' => 1, 'alat' => 1]])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        // Kelola akun & pengaturan tetap khusus Super Admin.
        $this->as('administrasi@lab.local')->get('/admin/pengguna')->assertForbidden();
        $this->as('superadmin@lab.local')->get('/admin/pengguna')->assertOk();
    }

    public function test_paraf_digambar_jabatan_dipilih_dan_lhp_tidak_diunduh_pengguna(): void
    {
        $p = $this->ajukan();
        $this->verifikasi($p);
        $this->bayarLunas($p);
        $this->suratTugas($p);
        $this->ujiSemua($p);

        // Paraf wajib digambar; selain PNG ditolak.
        $this->as('penyelia.organo@lab.local')->post("/permohonan/{$p->id}/paraf")->assertSessionHasErrors('paraf');
        $this->as('penyelia.organo@lab.local')->post("/permohonan/{$p->id}/paraf", ['paraf' => 'data:image/png;base64,'.base64_encode('bukan png')])->assertSessionHasErrors('paraf');
        $this->parafSemua($p);
        $this->assertSame(3, \App\Models\Persetujuan::query()->where('permohonan_id', $p->id)->where('jenis', 'PARAF')->whereNotNull('paraf_gambar')->count());
        $this->periksa($p);
        $this->parafAdmin($p);
        $this->assertNotNull(\App\Models\Persetujuan::query()->where('permohonan_id', $p->id)->where('jenis', 'PARAF_ADM')->value('paraf_gambar'));

        // Jabatan penanda tangan wajib dipilih dan tercetak di LHP.
        $this->as('pj@lab.local')->post("/permohonan/{$p->id}/sahkan")->assertSessionHasErrors('jabatan');
        $this->as('pj@lab.local')->post("/permohonan/{$p->id}/sahkan", ['jabatan' => 'KEPALA'])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('KEPALA', $p->fresh()->lhu->jabatan_penanda);
        $this->as('operator@lab.local')->get("/permohonan/{$p->id}/lhp")->assertOk()
            ->assertSee('Kepala UPT')->assertDontSee('Kepala UPT/Penanggung jawab')->assertSee('Paraf:')->assertSee('Admin Lab');
        $this->as('operator@lab.local')->get("/permohonan/{$p->id}/lhp")->assertDontSee('alt="Paraf Penyelia Organoleptik"', false)->assertDontSee('alt="Paraf Penyelia Kimia"', false);
        $this->as('operator@lab.local')->get("/permohonan/{$p->id}/lhp.pdf")->assertOk();

        // Pengguna jasa hanya melihat status selesai.
        $this->assertSame(\App\Enums\Status::Selesai, $p->fresh()->status);
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}/lhp")->assertNotFound();
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('diserahkan oleh petugas di kantor Balai')->assertDontSee('LHP (PDF)');
    }

    public function test_penanda_surat_tugas_dipilih_dan_penyelia_langsung_disposisi(): void
    {
        $pj2 = User::query()->where('email', 'pj@lab.local')->first()->replicate();
        $pj2->fill(['nama' => 'Dr. Contoh Penanggung', 'email' => 'pj2@lab.local', 'nip' => '19800101 200501 1 002'])->save();

        $p = $this->ajukan();
        $this->verifikasi($p);
        $this->bayarLunas($p);
        $this->as('admin@lab.local')->get("/permohonan/{$p->id}")->assertOk()
            ->assertSee('Kepala/Penanggung Jawab Laboratorium Pengujian')->assertSee('Ricat Pahlefi Hidayat')->assertSee('Dr. Contoh Penanggung');
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/surat-tugas", ['penanda' => 'xx'])->assertSessionHasErrors('penanda');
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/surat-tugas", ['penanda' => 'u'.$pj2->id])->assertSessionHas('success');
        $st = \App\Models\SuratTugas::query()->where('permohonan_id', $p->id)->first();
        $this->assertSame(['Dr. Contoh Penanggung', '19800101 200501 1 002'], [$st->penanda_nama, $st->penanda_nip]);
        $this->as('penyelia.kimia@lab.local')->get("/permohonan/{$p->id}/surat-tugas.pdf")->assertOk();

        // Penyelia tidak lagi menekan "Tangani sampel"; analis masih bisa.
        $this->as('penyelia.kimia@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('Disposisi analis')->assertDontSee('Tangani sampel');
        $this->as('kimia@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('Tangani sampel');
    }

    public function test_tarif_dan_nama_parameter_sesuai_daftar_resmi(): void
    {
        $this->assertSame(150000, ParameterUji::query()->where('kode', 'CK')->value('tarif'));
        $this->assertSame('Total Coliform', ParameterUji::query()->where('kode', 'C')->value('nama'));
        $this->assertSame(160000, ParameterUji::query()->where('kode', 'CM')->value('tarif'));
        $this->assertSame('Escherichia coli & Coliform (Air/Es proses UPI)', ParameterUji::query()->where('kode', 'CM')->value('nama'));
        $this->assertFalse(ParameterUji::query()->where('kode', 'ECM')->where('aktif', true)->exists());
    }
    public function test_pangkat_hasil_dicetak_sebagai_superskrip(): void
    {
        $this->assertSame('2,5 x 10<sup>3</sup> kol/g', \App\Support\Pangkat::html('2,5 x 10³ kol/g'));
        $this->assertSame('1,0 x 10<sup>-2</sup>', \App\Support\Pangkat::html('1,0 x 10⁻²'));
        $this->assertSame('5 x 10<sup>5</sup> &amp; 10<sup>-1</sup>', \App\Support\Pangkat::html('5 x 10^5 & 10^{-1}'));
        $this->assertSame('&lt;b&gt;ND&lt;/b&gt;', \App\Support\Pangkat::html('<b>ND</b>'));

        $p = $this->ajukan('pengguna@lab.local', ['A', 'E1', 'H']);
        $this->verifikasi($p);
        $this->bayarLunas($p);
        $this->suratTugas($p);
        $this->ujiLab($p, 'mikro@lab.local', true, '2,5 x 10³ kol/g');
        $this->ujiLab($p, 'kimia@lab.local');
        $this->parafSemua($p, ['penyelia.mikro@lab.local', 'penyelia.kimia@lab.local']);
        $this->periksa($p);
        $this->parafAdmin($p);
        $this->sahkan($p);
        $this->as('operator@lab.local')->get("/permohonan/{$p->id}/lhp")->assertOk()
            ->assertSee('2,5 x 10<sup>3</sup> kol/g', false)->assertSee('5,0 x 10<sup>5</sup> kol/g', false);
        $this->as('operator@lab.local')->get("/permohonan/{$p->id}/lhp.pdf")->assertOk();
    }
    public function test_agenda_pemeriksaan_mikro_diisi_dan_dicetak_sesuai_formulir(): void
    {
        Carbon::setTestNow('2026-11-26 09:00:00');
        $p = $this->ajukan('pengguna@lab.local', ['S', 'VP', 'E2', 'H']);
        $this->verifikasi($p);
        $this->bayarLunas($p);
        $this->suratTugas($p);
        [$s, $vp] = [$p->parameter()->where('kode', 'S')->value('id'), $p->parameter()->where('kode', 'VP')->value('id')];

        $this->as('mikro@lab.local')->get("/permohonan/{$p->id}")->assertOk()
            ->assertSee('AGENDA PEMERIKSAAN <i>Salmonella</i> sp.', false)->assertSee('METODE SNI ISO 6579.2017')
            ->assertSee("name=\"lk[{$s}][1][r0.tsia]\"", false)->assertSee("name=\"lk[{$s}][1][g2.enrichment]\"", false)
            ->assertSee('AGENDA PEMERIKSAAN <i>Vibrio Parahaemolyticus</i>', false)->assertSee('10⁻³.3')
            ->assertSee('AGENDA PEMERIKSAAN <i>E. coli</i> / Coliform', false)->assertSee('L/OK/SUB II/8.6c')
            ->assertSee("name=\"hasil[{$s}][hasil]\"", false);

        $this->as('mikro@lab.local')->post("/permohonan/{$p->id}/hasil", ['aksi' => 'simpan',
            'lk' => [$s => [1 => ['bpw' => '+', 'g0.enrichment' => '+', 'r1.plating' => 'PH', 'r3.vi' => ' - ', 'r9.tsia' => 'x', 'liar' => 'x', 'hasil' => 'abaikan']]],
            'hasil' => [$s => ['hasil' => 'Negatif/25 g', 'memenuhi' => '1']],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $baris = \App\Models\PermohonanParameter::find($s);
        $this->assertSame(['s1' => ['bpw' => '+', 'g0.enrichment' => '+', 'r1.plating' => 'PH', 'r3.vi' => '-']], $baris->lembar_kerja);
        $this->assertSame('Negatif/25 g', $baris->hasil);

        // Kimia tidak bisa mengisi lembar parameter mikro.
        $this->as('kimia@lab.local')->post("/permohonan/{$p->id}/hasil", ['aksi' => 'simpan', 'lk' => [$vp => [1 => ['r0.tcbs' => 'H']]]]);
        $this->assertNull(\App\Models\PermohonanParameter::find($vp)->lembar_kerja);

        $this->as('penyelia.mikro@lab.local')->get('/agenda-mikro.pdf?jenis=S&bulan=2026-11')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        foreach (['CM', 'E1', 'E2', 'SA', 'VC', 'VP'] as $jenis) {
            $this->as('mikro@lab.local')->get("/agenda-mikro.pdf?jenis={$jenis}&bulan=2026-11")->assertOk();
        }
        $this->as('mikro@lab.local')->get('/agenda-mikro.pdf?jenis=X')->assertNotFound();
        $this->as('mikro@lab.local')->get('/agenda-mikro.pdf?jenis=S&bulan=abc')->assertOk();
        $this->as('pengguna@lab.local')->get('/agenda-mikro.pdf?jenis=S')->assertForbidden();
    }
}
