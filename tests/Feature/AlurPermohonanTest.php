<?php

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\Lhu;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlurPermohonanTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function u(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function as(string $email): static
    {
        $u = $this->u($email);

        return $this->actingAs($u)->withSession(['auth_version' => $u->auth_version, 'login_at' => time()]);
    }

    private function png(string $name = 'bukti.png'): UploadedFile
    {
        $f = tmpfile();
        fwrite($f, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $path = stream_get_meta_data($f)['uri'];
        $copy = sys_get_temp_dir().'/'.uniqid().'.png';
        copy($path, $copy);

        return new UploadedFile($copy, $name, 'image/png', null, true);
    }

    private function ajukan(): Permohonan
    {
        $this->as('pengguna@lab.local')->post('/permohonan', [
            'nama_sampel' => 'Tuna loin beku', 'jenis_komoditas' => 'Ikan beku', 'jumlah_sampel' => 2,
            'asal_sampel' => 'Muncar, Banyuwangi', 'tujuan' => 'EKSPOR', 'jenis_bayar' => 'MANDIRI',
        ])->assertRedirect();

        return Permohonan::query()->latest('id')->firstOrFail();
    }

    private function sampaiLab(): Permohonan
    {
        $p = $this->ajukan();
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid', 'kode_billing' => '820250000000001']);
        $this->as('pengguna@lab.local')->post("/permohonan/{$p->id}/bayar", ['ntpn' => 'ABCD1234EFGH5678', 'bukti' => $this->png()]);
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/konfirmasi-bayar", ['keputusan' => 'terima']);

        return $p->fresh();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_alur_lengkap_sampai_lhu_terbit_dan_terverifikasi(): void
    {
        $p = $this->sampaiLab();
        $this->assertSame(Status::DiLab, $p->status);
        $this->assertSame(2 * 425000 + 10000, $p->tagihan);

        foreach (['nekropsi@lab.local' => 'NEGATIF', 'mikro@lab.local' => 'NEGATIF', 'kimia@lab.local' => 'NEGATIF'] as $email => $hasil) {
            $this->as($email)->post("/permohonan/{$p->id}/hasil", ['hasil' => $hasil])->assertRedirect('/beranda');
        }
        $this->assertSame(Status::MenungguValidasi, $p->fresh()->status);

        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/validasi-lhu")->assertSessionHas('success');
        $lhu = Lhu::query()->where('permohonan_id', $p->id)->firstOrFail();
        $this->assertSame('LULUS', $lhu->status_izin->value);
        $this->assertMatchesRegularExpression('#^0001/.+/\d{4}$#', $lhu->nomor);

        $this->get('/verifikasi/'.$lhu->kode_verifikasi)->assertOk()->assertSee('LHU asli');
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}/lhu")->assertOk()->assertSee('Lulus uji, bisa ekspor');
    }

    public function test_satu_hasil_positif_membuat_lhu_tidak_lulus(): void
    {
        $p = $this->sampaiLab();
        $this->as('nekropsi@lab.local')->post("/permohonan/{$p->id}/hasil", ['hasil' => 'NEGATIF']);
        $this->as('mikro@lab.local')->post("/permohonan/{$p->id}/hasil", ['hasil' => 'POSITIF']);
        $this->as('kimia@lab.local')->post("/permohonan/{$p->id}/hasil", ['hasil' => 'NEGATIF']);
        $this->assertSame('TIDAK_LULUS', Lhu::query()->where('permohonan_id', $p->id)->first()->status_izin->value);
    }

    public function test_perubahan_isi_lhu_di_database_terdeteksi(): void
    {
        $p = $this->sampaiLab();
        foreach (['nekropsi', 'mikro', 'kimia'] as $l) {
            $this->as("$l@lab.local")->post("/permohonan/{$p->id}/hasil", ['hasil' => $l === 'kimia' ? 'POSITIF' : 'NEGATIF']);
        }
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/validasi-lhu");
        $lhu = Lhu::query()->where('permohonan_id', $p->id)->firstOrFail();
        Lhu::query()->whereKey($lhu->id)->update(['status_izin' => 'LULUS']); // pemalsuan langsung di DB

        $this->get('/verifikasi/'.$lhu->kode_verifikasi)->assertOk()->assertSee('tidak cocok');
    }

    public function test_pengguna_lain_tidak_bisa_melihat_permohonan_orang_lain(): void
    {
        $p = $this->ajukan();
        $lain = User::query()->create(['nama' => 'Lain', 'email' => 'lain@x.id', 'password' => 'Rahasia123', 'role' => 'pengguna_jasa']);
        $this->actingAs($lain)->withSession(['auth_version' => 0, 'login_at' => time()])
            ->get("/permohonan/{$p->id}")->assertNotFound();
        $this->actingAs($lain)->withSession(['auth_version' => 0, 'login_at' => time()])
            ->post("/permohonan/{$p->id}/bayar", ['ntpn' => 'ABCD1234EFGH5678'])->assertNotFound();
    }

    public function test_peran_dibatasi_per_route(): void
    {
        $p = $this->ajukan();
        $this->as('pengguna@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid', 'kode_billing' => '820250000000001'])->assertForbidden();
        $this->as('nekropsi@lab.local')->get('/admin/pengguna')->assertForbidden();
        $this->as('admin@lab.local')->get('/admin/pengguna')->assertForbidden();
        $this->as('superadmin@lab.local')->get('/admin/pengguna')->assertOk();
        $this->as('nekropsi@lab.local')->get("/permohonan/{$p->id}")->assertNotFound(); // belum di lab-nya
    }

    public function test_petugas_tidak_bisa_input_hasil_lab_lain_dan_tidak_melihat_identitas_klien(): void
    {
        $p = $this->sampaiLab();
        $this->as('kimia@lab.local')->post("/permohonan/{$p->id}/hasil", ['hasil' => 'NEGATIF'])->assertNotFound();
        $this->as('nekropsi@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertDontSee('CV Bahari Demo')->assertDontSee('820250000000001');
    }

    public function test_transisi_ganda_ditolak(): void
    {
        $p = $this->ajukan();
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid', 'kode_billing' => '820250000000001'])->assertSessionHas('success');
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'tolak', 'alasan' => 'Klik kedua dari tab lain'])->assertSessionHas('error');
        $this->assertSame(Status::MenungguPembayaran, $p->fresh()->status);
    }

    public function test_upload_palsu_ditolak_berdasarkan_isi_berkas(): void
    {
        $p = $this->ajukan();
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid', 'kode_billing' => '820250000000001']);
        // Isi bukan gambar meski bernama .png (teks biasa agar tidak dikarantina antivirus di Windows).
        $path = sys_get_temp_dir().'/'.uniqid('palsu').'.png';
        file_put_contents($path, '<html><body>bukan gambar</body></html>');
        $palsu = new UploadedFile($path, 'bukti.png', 'image/png', null, true);
        $this->as('pengguna@lab.local')->post("/permohonan/{$p->id}/bayar", ['ntpn' => 'ABCD1234EFGH5678', 'bukti' => $palsu])->assertSessionHasErrors('bukti');
        $this->assertSame(Status::MenungguPembayaran, $p->fresh()->status);
    }

    public function test_ntpn_tidak_boleh_dipakai_dua_kali(): void
    {
        $this->sampaiLab();
        $p2 = $this->ajukan();
        $this->as('admin@lab.local')->post("/permohonan/{$p2->id}/verifikasi", ['keputusan' => 'valid', 'kode_billing' => '820250000000002']);
        $this->as('pengguna@lab.local')->post("/permohonan/{$p2->id}/bayar", ['ntpn' => 'abcd1234efgh5678', 'bukti' => $this->png()])->assertSessionHasErrors('ntpn');
    }

    public function test_pengguna_belum_melihat_hasil_sebelum_lhu_tervalidasi(): void
    {
        $p = $this->sampaiLab();
        $this->as('nekropsi@lab.local')->post("/permohonan/{$p->id}/hasil", ['hasil' => 'POSITIF', 'catatan' => 'Ditemukan lesi']);
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertDontSee('Ditemukan lesi')->assertDontSee('badge-bad', false);
        $this->as('pengguna@lab.local')->get("/permohonan/{$p->id}/lhu")->assertNotFound();
    }
}
