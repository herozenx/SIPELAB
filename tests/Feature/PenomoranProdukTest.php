<?php

namespace Tests\Feature;

use App\Models\Permohonan;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\AlurUji;
use Tests\TestCase;

/**
 * Revisi 3, dicocokkan dengan buku sampel & LHP Balai 2026:
 * nomor urut harian lintas jenis, nomor LHP = nomor agenda registrasi, bulan LHP = bulan diterima,
 * beberapa produk dalam satu permohonan (akhiran A, B, C; satu nomor LHP, satu halaman per produk).
 */
class PenomoranProdukTest extends TestCase
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

    /** Eksternal: tanpa billing di awal, langsung surat tugas -> uji -> periksa -> paraf -> sah. */
    private function sampaiSahEksternal(Permohonan $p, array $penyelia): void
    {
        $this->suratTugas($p);
        foreach ($p->parameter()->with('lab')->get()->pluck('lab_id')->unique() as $labId) {
            $email = ['Organoleptik' => 'organo', 'Mikrobiologi' => 'mikro', 'Kimia' => 'kimia', 'Radionuklida' => 'radio'][\App\Models\Lab::query()->find($labId)->nama];
            $this->ujiLab($p, "{$email}@lab.local");
        }
        $this->periksa($p);
        $this->parafSemua($p, $penyelia);
        $this->sahkan($p);
    }

    public function test_nomor_lhp_mengikuti_nomor_agenda_dan_bulan_sampel_diterima(): void
    {
        // Seperti LHU Cesium: SP.E/290626.02 diterima 29 Juni, disahkan 1 Juli -> 0437/.../VI/2026.
        Carbon::setTestNow('2026-06-29 09:00:00');
        $a = $this->ajukan('eksternal@lab.local', ['H']);
        $this->verifikasi($a, 'EKSTERNAL');
        $b = $this->ajukan('eksternal@lab.local', ['H']);
        $this->verifikasi($b, 'EKSTERNAL');
        $this->assertSame([1, 2], [$a->fresh()->no_agenda, $b->fresh()->no_agenda]);
        $this->assertSame(2026, $a->fresh()->tahun_agenda);

        Carbon::setTestNow('2026-07-01 10:00:00');
        $this->sampaiSahEksternal($b, ['penyelia.kimia@lab.local']); // disahkan lebih dulu, nomornya tetap 0002
        $this->sampaiSahEksternal($a, ['penyelia.kimia@lab.local']);

        $this->assertSame('0002/LAB/BPPMHKP.SBY.II/KI.310/VI/2026', $b->fresh()->lhu->nomor);
        $this->assertSame('0001/LAB/BPPMHKP.SBY.II/KI.310/VI/2026', $a->fresh()->lhu->nomor);

        // LHP: judul & kota sesuai dokumen asli.
        $this->as('operator@lab.local')->get("/permohonan/{$a->id}/lhp")->assertOk()
            ->assertSee('Hasil Uji')->assertSee('Test Result')->assertSee('Surabaya, 1 Juli 2026')
            ->assertSee('mewakili populasi yang diambil');
    }

    public function test_agenda_melanjutkan_nomor_lhp_lama_dan_tidak_terpakai_permohonan_ditolak(): void
    {
        Carbon::setTestNow('2026-11-24 09:00:00');
        // LHP lama (sebelum revisi ini) sudah memakai nomor urut 5 tahun ini.
        $lama = $this->ajukan('eksternal@lab.local', ['H']);
        $this->verifikasi($lama, 'EKSTERNAL');
        $lama->refresh();
        $lama->forceFill(['no_agenda' => null, 'tahun_agenda' => null])->save();
        \App\Models\Lhu::query()->create(['permohonan_id' => $lama->id, 'nomor' => '0005/X/2026', 'tahun' => 2026, 'nomor_urut' => 5, 'status_izin' => \App\Enums\StatusIzin::Lulus]);

        // Permohonan yang ditolak tidak memakai nomor urut buku sampel maupun agenda.
        $tolak = $this->ajukan();
        $this->as('superadmin@lab.local')->post("/permohonan/{$tolak->id}/verifikasi", ['keputusan' => 'tolak', 'alasan' => 'Data tidak lengkap'])->assertSessionHas('success');
        $this->assertSame('PMH-241126-002', $tolak->fresh()->kode);
        $this->assertNull($tolak->fresh()->kode_sampel);

        $p = $this->ajukan();
        $this->verifikasi($p);
        $p->refresh();
        $this->assertSame('SP.M/241126.02', $p->kode_sampel); // .01 dipakai $lama, bukan oleh yang ditolak
        $this->assertSame(6, $p->no_agenda);
    }

    public function test_beberapa_produk_satu_kode_berakhiran_dan_satu_nomor_lhp(): void
    {
        Carbon::setTestNow('2026-03-02 09:00:00');
        [$o, $h, $vp] = [$this->idParameter('O')[0], $this->idParameter('H')[0], $this->idParameter('VP')[0]];
        $this->as('eksternal@lab.local')->post('/permohonan', [
            'jenis_layanan' => 'MANDIRI', 'alamat' => 'Jl. Sunan Giri 21, Gresik', 'setuju' => ['metode' => 1, 'biaya' => 1, 'waktu' => 1],
            'produk' => [
                ['komoditas' => 'Moluska', 'nama' => 'Kerang Darah Segar (P1)', 'jumlah' => 1, 'berat' => '1 kg', 'parameter' => [$vp]],
                ['komoditas' => 'Moluska', 'nama' => 'Kerang Darah Segar (P2)', 'jumlah' => 2, 'berat' => '1 kg', 'parameter' => [$vp, $o], 'jumlah_uji' => [$vp => 2]],
                ['komoditas' => 'Ikan', 'nama' => 'Tuna Segar', 'jumlah' => 1, 'berat' => '500 g', 'parameter' => [$h]],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $p = Permohonan::query()->latest('id')->firstOrFail();
        $this->assertSame('Kerang Darah Segar (P1), Kerang Darah Segar (P2), Tuna Segar', $p->nama_sampel);
        $this->assertSame(4, $p->jumlah_sampel);
        $this->assertSame(3, $p->produk()->count());
        $this->assertSame(4, $p->parameter()->count());
        $this->assertSame(2, $p->parameter()->where('kode', 'VP')->where('produk_id', $p->produk[1]->id)->value('jumlah'));
        $this->assertSame(1, $p->parameter()->where('kode', 'O')->value('jumlah')); // jumlah uji tidak ikut jumlah sampel
        $this->assertSame('Moluska, Ikan', $p->jenis_komoditas);
        $this->assertSame(['1 kg', '1 kg', '500 g'], $p->produk()->pluck('berat')->all());

        $this->verifikasi($p, 'EKSTERNAL');
        $p->refresh();
        $this->assertSame('SP.PE/020326.01', $p->kode_sampel);
        $this->assertSame(['SP.PE/020326.01A', 'SP.PE/020326.01B', 'SP.PE/020326.01C'], $p->produk()->pluck('kode')->all());

        $this->as('admin@lab.local')->get("/permohonan/{$p->id}")->assertOk()
            ->assertSee('SP.PE/020326.01B · Kerang Darah Segar (P2)')->assertSee('Produk (3)');
        $this->as('operator@lab.local')->get("/permohonan/{$p->id}/bukti-terima.pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');

        Carbon::setTestNow('2026-03-09 09:00:00');
        $this->sampaiSahEksternal($p, ['penyelia.organo@lab.local', 'penyelia.mikro@lab.local', 'penyelia.kimia@lab.local']);
        $p->refresh();
        $this->assertSame('0001/LAB/BPPMHKP.SBY.II/KI.310/III/2026', $p->lhu->nomor);
        $this->assertTrue(app(\App\Services\Workflow::class)->lhuUtuh($p->lhu));

        // Satu halaman per produk, nomor LHP sama di setiap halaman.
        $lhp = $this->as('operator@lab.local')->get("/permohonan/{$p->id}/lhp")->assertOk()
            ->assertSee('SP.PE/020326.01A')->assertSee('SP.PE/020326.01B')->assertSee('SP.PE/020326.01C')
            ->assertSee('Kerang Darah Segar (P2)')->assertSee('Tuna Segar');
        $this->assertSame(3, substr_count($lhp->getContent(), $p->lhu->nomor.'</p>'));
        $pdf = $this->as('operator@lab.local')->get("/permohonan/{$p->id}/lhp.pdf");
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Hasil diubah langsung di database setelah disahkan -> terdeteksi tidak utuh.
        $p->produk()->where('urut', 2)->update(['nama' => 'Diubah']);
        $this->assertFalse(app(\App\Services\Workflow::class)->lhuUtuh($p->lhu->fresh()));
    }

    public function test_form_produk_divalidasi_per_produk(): void
    {
        $this->as('pengguna@lab.local')->get('/permohonan/baru')->assertOk()
            ->assertSee('Tambah produk')->assertSee('data-produk-template', false)->assertSee('name="produk[0][nama]"', false);
        $this->as('pengguna@lab.local')->post('/permohonan', [
            'jenis_layanan' => 'MANDIRI', 'alamat' => 'Jalan X', 'setuju' => ['metode' => 1, 'biaya' => 1, 'waktu' => 1],
            'produk' => [
                ['komoditas' => 'Ikan', 'nama' => 'Tuna', 'jumlah' => 1, 'berat' => '1 kg', 'parameter' => $this->idParameter('H')],
                ['komoditas' => 'Ikan', 'nama' => 'Cakalang', 'jumlah' => 1, 'berat' => '1 kg'],
            ],
        ])->assertSessionHasErrors('produk.1.parameter');
        $this->assertSame(0, Permohonan::query()->count());
    }

    public function test_pengaturan_bawaan_sesuai_dokumen_balai(): void
    {
        $this->assertSame('Surabaya', Setting::get('kota_lhp'));
        $this->assertSame('Plt. Kepala Laboratorium Penguji', Setting::get('jabatan_kepala_lab'));
        $this->assertSame('19780424 200502 1 001', Setting::get('nip_kepala_lab'));
    }
}
