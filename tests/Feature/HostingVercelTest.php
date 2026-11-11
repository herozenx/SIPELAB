<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Mode hosting tanpa disk permanen (Vercel): berkas unggahan disimpan di database. */
class HostingVercelTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function as(string $email): static
    {
        $u = User::query()->where('email', $email)->firstOrFail();

        return $this->actingAs($u)->withSession(['auth_version' => $u->auth_version, 'login_at' => time()]);
    }

    public function test_bukti_bayar_disimpan_dan_diunduh_dari_database(): void
    {
        Storage::fake('local');
        config(['sipelab.berkas_di_database' => true]);

        $this->as('pengguna@lab.local')->post('/permohonan', [
            'nama_sampel' => 'Tuna', 'jenis_komoditas' => 'Ikan beku', 'jumlah_sampel' => 1,
            'asal_sampel' => 'Banyuwangi', 'tujuan' => 'EKSPOR',
        ]);
        $p = Permohonan::query()->latest('id')->firstOrFail();
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid', 'kode_billing' => '820260000000077']);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $path = sys_get_temp_dir().'/'.uniqid().'.png';
        file_put_contents($path, $png);
        $this->as('pengguna@lab.local')->post("/permohonan/{$p->id}/bayar", [
            'ntpn' => 'VRCL1234EFGH5678', 'bukti' => new UploadedFile($path, 'bukti setor.png', 'image/png', null, true),
        ])->assertSessionHas('success');

        $dok = Dokumen::query()->where('permohonan_id', $p->id)->firstOrFail();
        $this->assertSame([], Storage::disk('local')->allFiles()); // tidak ada yang ditulis ke disk
        $this->assertSame(base64_encode($png), DB::table('dokumen_isi')->where('dokumen_id', $dok->id)->value('isi'));

        $res = $this->as('admin@lab.local')->get("/permohonan/{$p->id}/dokumen/{$dok->id}");
        $res->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame($png, $res->getContent());
        $this->assertStringContainsString('attachment', (string) $res->headers->get('Content-Disposition'));

        // Pengguna lain tetap tidak bisa mengunduh.
        $lain = User::query()->where('email', 'eksternal@lab.local')->firstOrFail();
        $this->actingAs($lain)->withSession(['auth_version' => $lain->auth_version, 'login_at' => time()])
            ->get("/permohonan/{$p->id}/dokumen/{$dok->id}")->assertNotFound();
    }
}
