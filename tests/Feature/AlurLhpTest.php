<?php

namespace Tests\Feature;

use App\Enums\JenisBayar;
use App\Enums\Status;
use App\Enums\StatusIzin;
use App\Models\Dokumen;
use App\Models\Pengembalian;
use App\Models\Permohonan;
use App\Models\PermohonanParameter;
use App\Models\Persetujuan;
use App\Models\SuratTugas;
use App\Models\TahapLab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\AlurUji;
use Tests\TestCase;

/** Revisi 2: peran baru, parameter uji, registrasi & surat tugas, periksa-paraf-sah, pengembalian, LHP rahasia + QR. */
class AlurLhpTest extends TestCase
{
    use AlurUji, RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_alur_mandiri_lengkap_sampai_lhp_selesai(): void
    {
        Carbon::setTestNow('2026-11-19 09:00:00');
        $p = $this->ajukan();
        $this->assertSame(Status::Diajukan, $p->status);
        $this->assertSame(JenisBayar::Mandiri, $p->jenis_bayar);
        $this->assertSame('PMH-191126-001', $p->kode); // nomor permohonan; kode SP baru saat registrasi
        $this->assertNull($p->kode_sampel);
        $this->assertSame(['E1', 'H', 'O'], $p->parameter()->pluck('kode')->sort()->values()->all());

        // Administrator: verifikasi -> diterima; Operator: registrasi -> kode sampel SP.M, menunggu billing.
        $this->as('superadmin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid'])->assertSessionHas('success');
        $this->assertSame(Status::Diterima, $p->fresh()->status);
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('serahkan sampel ke loket');
        $this->registrasi($p);
        $p->refresh();
        $this->assertSame('SP.M/191126.01', $p->kode_sampel);
        $this->assertSame(Status::MenungguBilling, $p->status);
        $this->assertSame('Beku', $p->bentuk);

        // Bendahara: billing resmi (dengan berkas) -> pengguna melihat & mengunggah bukti tanpa NTPN.
        $this->as('bendahara@lab.local')->post("/permohonan/{$p->id}/billing", [
            'kode_billing' => '820260000000001', 'tagihan' => 'Rp25.000', 'berkas_billing' => $this->png('billing.png'),
        ])->assertSessionHas('success');
        $p->refresh();
        $this->assertSame(Status::MenungguPembayaran, $p->status);
        $this->assertSame(25000, $p->tagihan);
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}")->assertOk()
            ->assertSee('820260000000001')->assertSee('Lihat billing resmi')->assertDontSee('name="ntpn"', false);
        $this->as('pengguna@lab.local')->post("/permohonan/{$p->id}/bayar", ['bukti' => $this->png()])->assertSessionHas('success');
        $this->assertSame(Status::MenungguKonfirmasiBayar, $p->fresh()->status);
        $this->as('bendahara@lab.local')->post("/permohonan/{$p->id}/konfirmasi-bayar", ['keputusan' => 'terima', 'ntpn' => 'ABCD1234EFGH5678'])->assertSessionHas('success');
        $this->assertSame(Status::SiapUji, $p->fresh()->status);

        // Operator: surat tugas per bidang ke penyelia -> paralel di 3 lab.
        $this->suratTugas($p);
        $p->refresh();
        $this->assertSame(Status::DiLab, $p->status);
        $surat = SuratTugas::query()->where('permohonan_id', $p->id)->with(['lab', 'penyelia'])->get();
        $this->assertEqualsCanonicalizing(['Organoleptik', 'Mikrobiologi', 'Kimia'], $surat->pluck('lab.nama')->all());
        $this->assertEqualsCanonicalizing(['Penyelia Organoleptik', 'Penyelia Mikrobiologi', 'Penyelia Kimia'], $surat->pluck('penyelia.nama')->all());
        $this->assertSame(['SP.M/191126.01/FPPCU/SUB II/XI/2026'], $surat->pluck('nomor')->unique()->values()->all());
        $this->assertSame(3, TahapLab::query()->where('permohonan_id', $p->id)->whereNull('selesai_at')->count());
        $st = $this->as('penyelia.kimia@lab.local')->get("/permohonan/{$p->id}/surat-tugas.pdf");
        $st->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $st->getContent());
        $this->as('radio@lab.local')->get("/permohonan/{$p->id}/surat-tugas.pdf")->assertNotFound();
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}/surat-tugas.pdf")->assertNotFound();

        // Analis tiap lab; status baru berpindah setelah lab terakhir selesai.
        $this->ujiLab($p, 'organo@lab.local', true, '7,0');
        $this->assertSame(Status::DiLab, $p->fresh()->status);
        $this->ujiLab($p, 'mikro@lab.local', true, 'Negatif/25 g');
        $this->ujiLab($p, 'kimia@lab.local', true, 'ND');
        $this->assertSame(Status::MenungguPemeriksaan, $p->fresh()->status);

        // Admin Lab periksa -> penyelia paraf (satu per bidang) -> PJ sahkan.
        $this->periksa($p);
        $this->assertSame(Status::MenungguParaf, $p->fresh()->status);
        $this->as('penyelia.organo@lab.local')->post("/permohonan/{$p->id}/paraf")->assertSessionHas('success');
        $this->as('penyelia.organo@lab.local')->post("/permohonan/{$p->id}/paraf")->assertSessionHas('error'); // tidak dobel
        $this->assertSame(Status::MenungguParaf, $p->fresh()->status);
        $this->parafSemua($p, ['penyelia.mikro@lab.local', 'penyelia.kimia@lab.local']);
        $this->assertSame(Status::MenungguPengesahan, $p->fresh()->status);
        $this->sahkan($p);

        $p->refresh();
        $this->assertSame(Status::Selesai, $p->status);
        $lhu = $p->lhu;
        $this->assertSame('0001/LAB/BPPMHKP.SBY.II/KI.310/XI/2026', $lhu->nomor);
        $this->assertSame(StatusIzin::Lulus, $lhu->status_izin);
        $this->assertSame(4, $lhu->versi_sidik);
        $this->assertSame(1, $p->no_agenda);
        $this->assertSame(['PARAF', 'PARAF', 'PARAF', 'PERIKSA', 'SAH'], Persetujuan::query()->where('permohonan_id', $p->id)->pluck('jenis')->sort()->values()->all());

        // LHP: petugas kantor membuka pratinjau (dengan QR); pengguna jasa mengunduh PDF setelah selesai.
        $this->as('operator@lab.local')->get("/permohonan/{$p->id}/lhp")->assertOk()
            ->assertSee($lhu->nomor)->assertSee('SP.M/191126.01')->assertSee('Jalan Rungkut Madya')
            ->assertSee('Histamin')->assertSee('Pindai untuk cek keaslian')->assertSee('<svg', false)->assertSee('NIP. 198001012005012001');
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}")->assertOk()
            ->assertSee('LHP selesai')->assertSee('LHP (PDF)')->assertSee('ND')->assertDontSee('Pratinjau')->assertDontSee('Analis Kimia');
        $this->as('mikro@lab.local')->get("/permohonan/{$p->id}/lhp")->assertNotFound();

        // Cek keaslian publik.
        $this->get('/verifikasi/'.$lhu->kode_verifikasi)->assertOk()->assertSee('LHP asli')->assertSee('SP.M/191126.01');
    }

    public function test_eksternal_dan_surveilen_ditetapkan_operator_dan_dibayar_di_belakang(): void
    {
        Carbon::setTestNow('2026-11-19 09:00:00');
        $p = $this->ajukan('eksternal@lab.local', ['E1', 'S']);
        $this->verifikasi($p, 'EKSTERNAL');
        $p->refresh();
        $this->assertSame('SP.PE/191126.01', $p->kode_sampel);
        $this->assertSame(Status::SiapUji, $p->status); // tanpa billing di awal
        $this->assertNull($p->kode_billing);

        $this->suratTugas($p);
        $this->assertSame(1, SuratTugas::query()->where('permohonan_id', $p->id)->count()); // hanya Mikrobiologi
        $this->ujiLab($p, 'mikro@lab.local');
        $this->periksa($p);
        $this->parafSemua($p, ['penyelia.mikro@lab.local']);
        $this->sahkan($p);

        $p->refresh();
        $this->assertSame(Status::MenungguBilling, $p->status);
        $this->assertNotNull($p->lhu->nomor);
        $this->as('eksternal@lab.local')->get('/permohonan')->assertOk()->assertSee('LHP selesai');

        $this->bayarLunas($p, '820260000000002');
        $this->assertSame(Status::Selesai, $p->fresh()->status);

        $s = $this->ajukan('eksternal@lab.local', ['H']);
        $this->verifikasi($s, 'SURVEILEN');
        $this->assertSame('SP.S/191126.02', $s->fresh()->kode_sampel); // urut harian lanjut dari SP.PE/191126.01
        $this->assertSame(Status::SiapUji, $s->fresh()->status);
    }

    public function test_pengguna_jasa_hanya_bisa_mandiri(): void
    {
        $this->as('pengguna@lab.local')->get('/permohonan/baru')->assertOk()
            ->assertSee('Mandiri')->assertDontSee('name="jenis_bayar"', false)->assertSee('Histamin')->assertSee('Cesium (Cs-137)');
        $p = $this->ajukan('pengguna@lab.local', ['H'], ['jenis_bayar' => 'EKSTERNAL']);
        $this->assertSame(JenisBayar::Mandiri, $p->jenis_bayar);

        // Wajib memilih parameter.
        $this->as('pengguna@lab.local')->post('/permohonan', [
            'nama_sampel' => 'Tuna', 'jenis_komoditas' => 'Ikan', 'jumlah_sampel' => 1, 'asal_sampel' => 'X', 'alamat' => 'Jalan X', 'tujuan' => 'DOMESTIK',
        ])->assertSessionHasErrors('produk.0.parameter');
    }

    public function test_kode_sampel_urut_harian_berlanjut_lintas_jenis_layanan(): void
    {
        Carbon::setTestNow('2026-11-22 09:00:00');
        $kode = [];
        foreach (['MANDIRI', 'EKSTERNAL', 'SURVEILEN', 'MANDIRI', 'EKSTERNAL'] as $jenis) {
            $p = $this->ajukan();
            $this->verifikasi($p, $jenis);
            $kode[] = $p->fresh()->kode_sampel;
        }
        // Seperti buku sampel: SP.E/060126.01–.03 lalu SP.S/060126.04 (nomor urut harian dipakai bersama).
        $this->assertSame(['SP.M/221126.01', 'SP.PE/221126.02', 'SP.S/221126.03', 'SP.M/221126.04', 'SP.PE/221126.05'], $kode);

        Carbon::setTestNow('2026-11-23 08:00:00');
        $p = $this->ajukan();
        $this->verifikasi($p, 'EKSTERNAL');
        $this->assertSame('SP.PE/231126.01', $p->fresh()->kode_sampel);
    }

    public function test_hak_akses_tiap_peran_dibatasi(): void
    {
        $p = $this->ajukan();
        // Verifikasi hanya Administrator; registrasi hanya Operator & Admin Lab.
        foreach (['pengguna', 'bendahara', 'operator', 'admin', 'penyelia.kimia'] as $akun) {
            $this->as("{$akun}@lab.local")->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid'])->assertForbidden();
        }
        $this->as('superadmin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid'])->assertSessionHas('success');
        $kaji = ['jenis_bayar' => 'MANDIRI', 'kaji' => ['personel' => 1, 'bahan' => 1, 'alat' => 1]];
        foreach (['pengguna', 'bendahara', 'superadmin', 'pj'] as $akun) {
            $this->as("{$akun}@lab.local")->post("/permohonan/{$p->id}/registrasi", $kaji)->assertForbidden();
        }

        // Kaji ulang wajib lengkap.
        $this->as('operator@lab.local')->post("/permohonan/{$p->id}/registrasi", ['jenis_bayar' => 'MANDIRI', 'kaji' => ['personel' => 1]])
            ->assertSessionHasErrors('kaji.bahan');
        $this->registrasi($p, 'MANDIRI', 'admin@lab.local');

        $this->as('operator@lab.local')->post("/permohonan/{$p->id}/billing", ['kode_billing' => '820260000000001', 'tagihan' => 1000])->assertForbidden();
        $this->bayarLunas($p);
        $this->as('bendahara@lab.local')->post("/permohonan/{$p->id}/surat-tugas")->assertForbidden();
        $this->as('operator@lab.local')->post("/permohonan/{$p->id}/surat-tugas")->assertForbidden();
        $this->suratTugas($p);

        // Analis lab yang tidak terlibat tidak bisa membuka; lab lain tidak bisa input parameter lab lain.
        $this->as('radio@lab.local')->get("/permohonan/{$p->id}")->assertNotFound();
        $this->as('radio@lab.local')->post("/permohonan/{$p->id}/hasil", ['aksi' => 'simpan'])->assertNotFound();
        $histamin = $p->parameter()->where('kode', 'H')->value('id');
        $this->as('mikro@lab.local')->post("/permohonan/{$p->id}/hasil", ['aksi' => 'simpan', 'hasil' => [$histamin => ['hasil' => 'X', 'memenuhi' => '1']]])->assertSessionHas('success');
        $this->assertNull(PermohonanParameter::query()->find($histamin)->hasil); // bukan parameter labnya: diabaikan

        $this->ujiSemua($p);
        $this->as('operator@lab.local')->post("/permohonan/{$p->id}/periksa", ['cek' => ['identitas' => 1, 'parameter' => 1, 'hasil' => 1]])->assertForbidden();
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/periksa", ['cek' => ['identitas' => 1, 'parameter' => 1, 'hasil' => 1]])->assertForbidden();
        $this->periksa($p);
        $this->as('penyelia.radio@lab.local')->post("/permohonan/{$p->id}/paraf")->assertNotFound();
        $this->as('mikro@lab.local')->post("/permohonan/{$p->id}/paraf")->assertForbidden();
        $this->parafSemua($p);
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/sahkan")->assertForbidden();
        $this->as('superadmin@lab.local')->post("/permohonan/{$p->id}/sahkan")->assertForbidden();
        $this->sahkan($p);

        // Menu admin hanya untuk Administrator.
        $this->as('admin@lab.local')->get('/admin/parameter')->assertForbidden();
        $this->as('superadmin@lab.local')->get('/admin/parameter')->assertOk()->assertSee('Parameter uji');
    }

    public function test_analis_hanya_melihat_parameter_labnya_tanpa_identitas_pelanggan(): void
    {
        $p = $this->ajukan();
        $this->verifikasi($p);
        $this->bayarLunas($p);
        $this->suratTugas($p);

        $this->as('kimia@lab.local')->get('/beranda')->assertOk()->assertSee($p->kode_sampel ?? $p->fresh()->kode_sampel);
        $this->as('kimia@lab.local')->get("/permohonan/{$p->id}")->assertOk()
            ->assertSee('Input hasil Lab Kimia')->assertSee('Histamin')
            ->assertDontSee('Escherichia coli')->assertDontSee('CV Bahari Demo')->assertDontSee('820260000000001');

        // Simpan sementara boleh belum lengkap; selesai wajib lengkap.
        $h = $p->parameter()->where('kode', 'H')->value('id');
        $this->as('kimia@lab.local')->post("/permohonan/{$p->id}/hasil", ['aksi' => 'simpan', 'hasil' => [$h => ['hasil' => 'ND']]])->assertSessionHas('success');
        $this->as('kimia@lab.local')->post("/permohonan/{$p->id}/hasil", ['aksi' => 'selesai', 'hasil' => [$h => ['hasil' => 'ND']]])->assertSessionHas('error');
        $this->assertNull(TahapLab::query()->where('permohonan_id', $p->id)->whereHas('lab', fn ($q) => $q->where('nama', 'Kimia'))->value('selesai_at'));
        $this->assertSame('ND', PermohonanParameter::query()->find($h)->hasil);
    }

    public function test_pengembalian_mengikuti_flowchart(): void
    {
        $p = $this->ajukan();
        $this->verifikasi($p);
        $this->bayarLunas($p);
        $this->suratTugas($p);
        $this->ujiSemua($p);
        $kimia = $this->u('kimia@lab.local')->lab_id;
        $mikro = $this->u('mikro@lab.local')->lab_id;

        // 1) Validasi hasil (Administrator): data belum benar/lengkap -> kembali ke pengujian (revisi).
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/kembalikan", ['lab_id' => $kimia, 'jenis' => 'REVISI', 'catatan' => 'Admin Lab tidak berwenang'])->assertForbidden();
        $this->as('superadmin@lab.local')->post("/permohonan/{$p->id}/kembalikan", ['jenis' => 'PERBAIKAN', 'catatan' => 'bukan pilihan di tahap ini'])->assertSessionHas('error');
        $this->as('superadmin@lab.local')->post("/permohonan/{$p->id}/kembalikan", ['lab_id' => $kimia, 'jenis' => 'REVISI', 'catatan' => 'Satuan histamin salah tulis'])->assertSessionHas('success');
        $p->refresh();
        $this->assertSame(Status::DiLab, $p->status);
        $this->assertSame('Negatif', $p->parameter()->where('kode', 'H')->value('hasil'));
        $this->as('kimia@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('Revisi data')->assertSee('Satuan histamin salah tulis');
        $this->ujiLab($p, 'kimia@lab.local', true, 'ND');
        $this->assertSame(Status::MenungguPemeriksaan, $p->fresh()->status);
        $this->assertNotNull(Pengembalian::query()->where('permohonan_id', $p->id)->value('selesai_at'));

        // 2) Paraf/validasi akhir tidak disetujui: perbaikan data -> kembali ke validasi hasil (Administrator).
        $this->periksa($p);
        $this->as('penyelia.kimia@lab.local')->post("/permohonan/{$p->id}/paraf");
        $this->as('penyelia.kimia@lab.local')->post("/permohonan/{$p->id}/kembalikan", ['lab_id' => $kimia, 'jenis' => 'REVISI', 'catatan' => 'revisi tidak berlaku di tahap paraf'])->assertSessionHas('error');
        $this->as('pj@lab.local')->post("/permohonan/{$p->id}/kembalikan", ['jenis' => 'PERBAIKAN', 'catatan' => 'Nama sampel di LHP salah ketik'])->assertSessionHas('success');
        $this->assertSame(Status::MenungguPemeriksaan, $p->fresh()->status);
        $this->assertSame(0, Persetujuan::query()->where('permohonan_id', $p->id)->count());
        $this->as('superadmin@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('Perlu perbaikan data')->assertSee('Nama sampel di LHP salah ketik');
        $this->periksa($p);
        $this->assertNotNull(Pengembalian::query()->where('jenis', 'PERBAIKAN')->value('selesai_at'));

        // 3) Perlu pengujian ulang -> lab: hasil dikosongkan, nilai lama disimpan. Penyelia hanya untuk bidangnya.
        $this->as('penyelia.organo@lab.local')->post("/permohonan/{$p->id}/kembalikan", ['lab_id' => $mikro, 'jenis' => 'UJI_ULANG', 'catatan' => 'coba lab lain'])->assertSessionHas('error');
        $this->as('penyelia.mikro@lab.local')->post("/permohonan/{$p->id}/kembalikan", ['lab_id' => $mikro, 'jenis' => 'UJI_ULANG', 'catatan' => 'Sampel terkontaminasi, uji ulang'])->assertSessionHas('success');
        $this->assertSame(Status::DiLab, $p->fresh()->status);
        $this->assertNull($p->parameter()->where('kode', 'E1')->value('hasil'));
        $k = Pengembalian::query()->where('permohonan_id', $p->id)->where('jenis', 'UJI_ULANG')->firstOrFail();
        $this->assertSame('Negatif', $k->data_lama[0]['hasil']);
    }

    public function test_bukti_terima_dan_lhp_pdf(): void
    {
        $p = $this->ajukan();
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}/bukti-terima.pdf")->assertNotFound(); // belum diregistrasi
        $this->verifikasi($p);
        $res = $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}/bukti-terima.pdf");
        $res->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $res->getContent());
        $this->as('eksternal@lab.local')->get("/permohonan/{$p->id}/bukti-terima.pdf")->assertNotFound();

        // Bukti Tagihan PNBP otomatis setelah billing terbit, lengkap dengan data pembayaran setelah lunas.
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}/bukti-tagihan.pdf")->assertNotFound();
        $this->as('bendahara@lab.local')->post("/permohonan/{$p->id}/billing", [
            'kode_billing' => '820261118170545', 'tagihan' => '500.000', 'nomor_aju' => 'AN16.0XI202600752', 'nomor_pnbp' => 'PN/16.0/XI/2026/00666',
            'billing_at' => '2026-11-18 10:21', 'billing_kedaluwarsa_at' => '2026-11-21 10:21',
        ])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame('AN16.0XI202600752', $p->nomor_aju);
        $this->assertSame('2026-11-21', $p->billing_kedaluwarsa_at->format('Y-m-d'));
        $tagihan = $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}/bukti-tagihan.pdf");
        $tagihan->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $tagihan->getContent());
        $this->as('eksternal@lab.local')->get("/permohonan/{$p->id}/bukti-tagihan.pdf")->assertNotFound();
        $this->as('pengguna@lab.local')->post("/permohonan/{$p->id}/bayar", ['bukti' => $this->png()]);
        $this->as('bendahara@lab.local')->post("/permohonan/{$p->id}/konfirmasi-bayar", [
            'keputusan' => 'terima', 'ntpn' => 'ABCD1234EFGH5678', 'bank_bayar' => 'BANK BRI', 'channel_bayar' => 'Teller', 'ntb' => '123456',
        ])->assertSessionHasNoErrors();
        $this->assertSame('BANK BRI', $p->fresh()->bank_bayar);
        $this->assertNotNull($p->fresh()->dibayar_at);

        $this->suratTugas($p);
        $this->ujiSemua($p);
        $this->periksa($p);
        $this->parafSemua($p);
        $this->sahkan($p);
        $pdf = $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}/lhp.pdf");
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->as('pj@lab.local')->get("/permohonan/{$p->id}/lhp.pdf")->assertOk();
        $this->as('kimia@lab.local')->get("/permohonan/{$p->id}/lhp.pdf")->assertNotFound();
    }

    public function test_hasil_berubah_setelah_paraf_menggagalkan_pengesahan_dan_lhp_diubah_terdeteksi(): void
    {
        $p = $this->ajukan();
        $this->verifikasi($p);
        $this->bayarLunas($p);
        $this->suratTugas($p);
        $this->ujiSemua($p);
        $this->periksa($p);
        $this->parafSemua($p);

        PermohonanParameter::query()->where('permohonan_id', $p->id)->where('kode', 'H')->update(['hasil' => '150 mg/kg']);
        $this->as('pj@lab.local')->post("/permohonan/{$p->id}/sahkan")->assertSessionHas('error');
        $this->assertSame(Status::MenungguPengesahan, $p->fresh()->status);

        PermohonanParameter::query()->where('permohonan_id', $p->id)->where('kode', 'H')->update(['hasil' => 'Negatif']);
        $this->sahkan($p);
        $kode = $p->fresh()->lhu->kode_verifikasi;
        $this->get('/verifikasi/'.$kode)->assertOk()->assertSee('LHP asli');

        PermohonanParameter::query()->where('permohonan_id', $p->id)->where('kode', 'H')->update(['hasil' => 'ND']);
        $this->get('/verifikasi/'.$kode)->assertOk()->assertSee('tidak cocok');
    }

    public function test_parameter_tidak_memenuhi_membuat_lhp_tidak_memenuhi(): void
    {
        $p = $this->ajukan('pengguna@lab.local', ['H']);
        $this->verifikasi($p);
        $this->bayarLunas($p);
        $this->suratTugas($p);
        $this->ujiLab($p, 'kimia@lab.local', false, '150 mg/kg');
        $this->periksa($p);
        $this->parafSemua($p, ['penyelia.kimia@lab.local']);
        $this->as('pj@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('tidak memenuhi');
        $this->sahkan($p);
        $this->assertSame(StatusIzin::TidakLulus, $p->fresh()->lhu->status_izin);
    }

    public function test_transisi_ganda_dan_berkas_palsu_ditolak(): void
    {
        $p = $this->ajukan();
        $this->verifikasi($p);
        $this->as('operator@lab.local')->post("/permohonan/{$p->id}/registrasi", [
            'jenis_bayar' => 'MANDIRI', 'kaji' => ['personel' => 1, 'bahan' => 1, 'alat' => 1],
        ])->assertSessionHas('error');
        $this->as('superadmin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid'])->assertSessionHas('error');

        $this->as('bendahara@lab.local')->post("/permohonan/{$p->id}/billing", ['kode_billing' => '820260000000001', 'tagihan' => 25000]);
        $palsu = sys_get_temp_dir().'/'.uniqid().'.png';
        file_put_contents($palsu, '<?php echo "x";');
        $this->as('pengguna@lab.local')->post("/permohonan/{$p->id}/bayar", ['bukti' => new UploadedFile($palsu, 'bukti.png', 'image/png', null, true)])
            ->assertSessionHasErrors('bukti');
        $this->assertSame(Status::MenungguPembayaran, $p->fresh()->status);
        $this->assertSame(0, Dokumen::query()->where('jenis', Dokumen::BUKTI_BAYAR)->count());
    }

    public function test_bukti_bayar_ditolak_lalu_unggah_ulang(): void
    {
        $p = $this->ajukan();
        $this->verifikasi($p);
        $this->as('bendahara@lab.local')->post("/permohonan/{$p->id}/billing", ['kode_billing' => '820260000000001', 'tagihan' => 25000]);
        $this->as('pengguna@lab.local')->post("/permohonan/{$p->id}/bayar", ['bukti' => $this->png()]);
        $this->as('bendahara@lab.local')->post("/permohonan/{$p->id}/konfirmasi-bayar", ['keputusan' => 'tolak'])->assertSessionHasErrors('catatan');
        $this->as('bendahara@lab.local')->post("/permohonan/{$p->id}/konfirmasi-bayar", ['keputusan' => 'tolak', 'catatan' => 'Dana belum masuk'])->assertSessionHas('success');
        $this->assertSame(Status::MenungguPembayaran, $p->fresh()->status);
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('Dana belum masuk');
    }

    public function test_pengguna_lain_tidak_bisa_melihat_permohonan(): void
    {
        $p = $this->ajukan();
        $this->as('eksternal@lab.local')->get("/permohonan/{$p->id}")->assertNotFound();
        $this->as('eksternal@lab.local')->post("/permohonan/{$p->id}/bayar", ['bukti' => $this->png()])->assertNotFound();
    }

    public function test_bukti_bayar_disimpan_di_database_untuk_hosting_tanpa_disk(): void
    {
        config(['sipelab.berkas_di_database' => true]);
        $p = $this->ajukan();
        $this->verifikasi($p);
        $this->as('bendahara@lab.local')->post("/permohonan/{$p->id}/billing", ['kode_billing' => '820260000000077', 'tagihan' => 25000]);
        $this->as('pengguna@lab.local')->post("/permohonan/{$p->id}/bayar", ['bukti' => $this->png('bukti setor.png')])->assertSessionHas('success');

        $dok = Dokumen::query()->where('permohonan_id', $p->id)->where('jenis', Dokumen::BUKTI_BAYAR)->firstOrFail();
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->as('bendahara@lab.local')->get("/permohonan/{$p->id}/dokumen/{$dok->id}")->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->as('eksternal@lab.local')->get("/permohonan/{$p->id}/dokumen/{$dok->id}")->assertNotFound();
    }

    public function test_dashboard_tiap_peran_menampilkan_antreannya(): void
    {
        $p = $this->ajukan();
        $this->as('superadmin@lab.local')->get('/beranda')->assertOk()->assertSee('Verifikasi permohonan')->assertSee($p->kode);
        $this->as('superadmin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid']);
        $this->as('operator@lab.local')->get('/beranda')->assertOk()->assertSee('Registrasi sampel')->assertSee($p->kode);
        $this->registrasi($p);
        $p->refresh();
        $this->as('bendahara@lab.local')->get('/beranda')->assertOk()->assertSee('Terbitkan billing SIMPONI')->assertSee($p->kode_sampel);
        $this->bayarLunas($p);
        $this->as('admin@lab.local')->get('/beranda')->assertOk()->assertSee('Terbitkan surat tugas')->assertSee($p->kode_sampel);
        $this->suratTugas($p);
        TahapLab::query()->where('permohonan_id', $p->id)->update(['batas_at' => now()->subDays(5)]);
        $this->as('mikro@lab.local')->get('/beranda')->assertOk()->assertSee($p->kode_sampel)->assertSee('Lewat');
        $this->as('superadmin@lab.local')->get('/beranda')->assertOk()->assertSee('Sampel melewati norma waktu');
        $this->ujiSemua($p);
        $this->as('superadmin@lab.local')->get('/beranda')->assertOk()->assertSee('Validasi hasil pengujian')->assertSee($p->kode_sampel);
        $this->periksa($p);
        $this->as('penyelia.mikro@lab.local')->get('/beranda')->assertOk()->assertSee('Menunggu paraf Anda')->assertSee('Periksa &amp; paraf', false);
        $this->parafSemua($p);
        $this->as('pj@lab.local')->get('/beranda')->assertOk()->assertSee('Validasi akhir LHP')->assertSee($p->kode_sampel);
        $this->sahkan($p);
        $this->as('operator@lab.local')->get('/beranda')->assertOk()->assertSee('LHP selesai terbaru')->assertSee('Cetak LHP');
        $this->as('mikro@lab.local')->get('/riwayat-uji')->assertOk()->assertSee($p->kode_sampel);
    }
}
