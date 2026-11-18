<?php

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\HasilParameter;
use App\Models\Lhu;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlurPermohonanTest extends TestCase
{
    use IsiLab;
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_alur_lengkap_sampai_lhp_terbit_dan_terverifikasi(): void
    {
        $p = $this->sampaiLabMandiri();
        $this->assertSame(Status::DiLab, $p->status);
        $this->assertSame(1 * 350000 + 10000, $p->tagihan); // Mikro 150rb + Kimia 200rb (Organoleptik & Radionuklida belum bertarif)

        $this->ujiSemuaLab($p);
        $this->assertSame(Status::MenungguCekAdministrasi, $p->fresh()->status);
        $this->assertSame(4, HasilParameter::query()->count()); // ALT, E, F, O

        $this->cekSesuai($p);
        $this->assertSame(Status::MenungguValidasi, $p->fresh()->status);

        $this->terbitkan($p)->assertSessionHas('success');
        $lhu = Lhu::query()->where('permohonan_id', $p->id)->firstOrFail();
        $this->assertMatchesRegularExpression('#^0001/LAB/BPPMHKP\.SBY\.II/KI\.310/[IVXLC]+/\d{4}$#', $lhu->nomor);
        $this->assertSame('Dyah Setyawati, S.Pi., MP.', $lhu->ttd_nama);
        $this->assertSame(Status::Selesai, $p->fresh()->status);

        $this->get('/verifikasi/'.$lhu->kode_verifikasi)->assertOk()->assertSee('LHU asli')->assertSee($p->fresh()->kode_sampel);
        $this->sebagai('pengguna@lab.local')->get("/permohonan/{$p->id}/lhu")->assertOk()
            ->assertSee('Angka Lempeng Total (ALT) Aerob')->assertSee('kol/g')->assertSee('SNI 2332.3-2015')
            ->assertDontSee('Lulus uji');
    }

    public function test_parameter_melebihi_tercatat_sebagai_rekap_internal(): void
    {
        $p = $this->sampaiLabMandiri();
        $this->ujiSemuaLab($p, ['S' => ['ulangan' => ['Positif/25 g'], 'melebihi' => 1]]);
        $this->assertSame('TIDAK_LULUS', Lhu::query()->where('permohonan_id', $p->id)->first()->status_izin->value);
    }

    public function test_perubahan_isi_lhp_di_database_terdeteksi(): void
    {
        $p = $this->sampaiLabMandiri();
        $this->ujiSemuaLab($p);
        $this->cekSesuai($p);
        $this->terbitkan($p);
        $lhu = Lhu::query()->where('permohonan_id', $p->id)->firstOrFail();
        $this->get('/verifikasi/'.$lhu->kode_verifikasi)->assertOk()->assertSee('LHU asli');

        // Pemalsuan hasil langsung di database.
        HasilParameter::query()->where('nama', 'Formalin')->update(['ulangan' => json_encode(['Positif'])]);
        $this->get('/verifikasi/'.$lhu->kode_verifikasi)->assertOk()->assertSee('tidak cocok');
    }

    public function test_pengguna_lain_tidak_bisa_melihat_permohonan_orang_lain(): void
    {
        $p = $this->ajukanSebagai('pengguna@lab.local');
        $lain = User::query()->create(['nama' => 'Lain', 'email' => 'lain@x.id', 'password' => 'Rahasia123', 'role' => 'pengguna_jasa']);
        $this->actingAs($lain)->withSession(['auth_version' => 0, 'login_at' => time()])
            ->get("/permohonan/{$p->id}")->assertNotFound();
        $this->actingAs($lain)->withSession(['auth_version' => 0, 'login_at' => time()])
            ->post("/permohonan/{$p->id}/bayar", ['ntpn' => 'ABCD1234EFGH5678'])->assertNotFound();
    }

    public function test_peran_dibatasi_per_route(): void
    {
        $p = $this->ajukanSebagai('pengguna@lab.local');
        $this->sebagai('pengguna@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid', 'kode_billing' => '820250000000001'])->assertForbidden();
        $this->sebagai('mikro@lab.local')->get('/admin/pengguna')->assertForbidden();
        $this->sebagai('admin@lab.local')->get('/admin/pengguna')->assertForbidden();
        $this->sebagai('superadmin@lab.local')->get('/admin/pengguna')->assertOk();
        $this->sebagai('mikro@lab.local')->get("/permohonan/{$p->id}")->assertNotFound(); // belum di lab-nya
    }

    public function test_petugas_tidak_bisa_input_hasil_lab_lain_dan_tidak_melihat_identitas_klien(): void
    {
        $p = $this->sampaiLabMandiri();
        $this->isiLab($p, 'kimia@lab.local', ['F' => ['Negatif']])->assertNotFound();
        $this->sebagai('mikro@lab.local')->get("/permohonan/{$p->id}")->assertOk()
            ->assertSee('Angka Lempeng Total')->assertDontSee('CV Bahari Demo')->assertDontSee('820250000000001')->assertDontSee('Jl. Raya Sedati');
    }

    public function test_transisi_ganda_ditolak(): void
    {
        $p = $this->ajukanSebagai('pengguna@lab.local');
        $this->sebagai('admin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid', 'kode_billing' => '820250000000001'])->assertSessionHas('success');
        $this->sebagai('admin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'tolak', 'alasan' => 'Klik kedua dari tab lain'])->assertSessionHas('error');
        $this->assertSame(Status::MenungguPembayaran, $p->fresh()->status);
    }

    public function test_upload_palsu_ditolak_berdasarkan_isi_berkas(): void
    {
        $p = $this->ajukanSebagai('pengguna@lab.local');
        $this->sebagai('admin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid', 'kode_billing' => '820250000000001']);
        // Isi bukan gambar meski bernama .png (teks biasa agar tidak dikarantina antivirus di Windows).
        $path = sys_get_temp_dir().'/'.uniqid('palsu').'.png';
        file_put_contents($path, '<html><body>bukan gambar</body></html>');
        $palsu = new UploadedFile($path, 'bukti.png', 'image/png', null, true);
        $this->sebagai('pengguna@lab.local')->post("/permohonan/{$p->id}/bayar", ['ntpn' => 'ABCD1234EFGH5678', 'bukti' => $palsu])->assertSessionHasErrors('bukti');
        $this->assertSame(Status::MenungguPembayaran, $p->fresh()->status);
    }

    public function test_ntpn_tidak_boleh_dipakai_dua_kali(): void
    {
        $this->sampaiLabMandiri();
        $p2 = $this->ajukanSebagai('pengguna@lab.local');
        $this->sebagai('admin@lab.local')->post("/permohonan/{$p2->id}/verifikasi", ['keputusan' => 'valid', 'kode_billing' => '820250000000002']);
        $this->sebagai('pengguna@lab.local')->post("/permohonan/{$p2->id}/bayar", ['ntpn' => 'abcd1234efgh5678', 'bukti' => $this->gambar()])->assertSessionHasErrors('ntpn');
    }

    public function test_pengguna_belum_melihat_hasil_sebelum_lhp_terbit(): void
    {
        $p = $this->sampaiLabMandiri();
        $this->isiLab($p, 'mikro@lab.local', ['ALT' => ['9,8 x 10^4']], ['catatan' => 'Catatan lembar kerja']);
        $this->sebagai('pengguna@lab.local')->get("/permohonan/{$p->id}")->assertOk()
            ->assertDontSee('Catatan lembar kerja')->assertDontSee('9,8')->assertDontSee('Angka Lempeng Total');
        $this->sebagai('pengguna@lab.local')->get("/permohonan/{$p->id}/lhu")->assertNotFound();
        $this->sebagai('pengguna@lab.local')->get("/permohonan/{$p->id}/lhp.docx")->assertNotFound();
    }
}
