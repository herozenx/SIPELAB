<?php

namespace Tests\Feature\Concerns;

use App\Enums\Status;
use App\Models\ParameterUji;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/** Langkah-langkah alur permohonan revisi 2 untuk dipakai bersama di tes fitur. */
trait AlurUji
{
    protected function u(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    protected function as(string $email): static
    {
        $u = $this->u($email);

        return $this->actingAs($u)->withSession(['auth_version' => $u->auth_version, 'login_at' => time()]);
    }

    protected function png(string $nama = 'bukti.png'): UploadedFile
    {
        $copy = sys_get_temp_dir().'/'.uniqid().'.png';
        file_put_contents($copy, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        return new UploadedFile($copy, $nama, 'image/png', null, true);
    }

    /** Paraf penyelia yang "digambar" (PNG 1x1 sebagai data URL, seperti hasil kanvas). */
    protected function paraf(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    }

    protected function idParameter(string ...$kode): array
    {
        return ParameterUji::query()->whereIn('kode', $kode)->pluck('id')->all();
    }

    /** Pengguna jasa mengajukan; bawaan: Organoleptik (O), E. coli (E1), Histamin (H). */
    protected function ajukan(string $email = 'pengguna@lab.local', array $kode = ['O', 'E1', 'H'], array $tambahan = []): Permohonan
    {
        $this->as($email)->post('/permohonan', $tambahan + [
            'jenis_layanan' => 'MANDIRI',
            'nama_sampel' => 'Udang Frozen', 'jenis_komoditas' => 'Krustasea', 'jumlah_sampel' => 1, 'berat' => '500 g',
            'alamat' => 'Jalan Rungkut Madya',
            'setuju' => ['metode' => 1, 'biaya' => 1, 'waktu' => 1],
            'parameter' => $this->idParameter(...$kode),
        ])->assertRedirect()->assertSessionHasNoErrors();

        return Permohonan::query()->latest('id')->firstOrFail();
    }

    /** Administrator memverifikasi permohonan, lalu Operator/Admin Lab meregistrasi sampel. */
    protected function verifikasi(Permohonan $p, string $jenis = 'MANDIRI', string $oleh = 'operator@lab.local'): void
    {
        $this->as('superadmin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['kesimpulan' => 'dapat', 'kaji' => ['personel' => 1, 'bahan' => 1, 'alat' => 1]])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->registrasi($p, $jenis, $oleh);
    }

    protected function registrasi(Permohonan $p, string $jenis = 'MANDIRI', string $oleh = 'operator@lab.local'): void
    {
        $this->as($oleh)->post("/permohonan/{$p->id}/registrasi", [
            'jenis_bayar' => $jenis, 'kaji' => ['personel' => 1, 'bahan' => 1, 'alat' => 1], 'bentuk' => 'Beku', 'suhu' => '-18',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    /** Billing -> unggah bukti -> konfirmasi bendahara. */
    protected function bayarLunas(Permohonan $p, string $kodeBilling = '820260000000001'): void
    {
        $this->as('bendahara@lab.local')->post("/permohonan/{$p->id}/billing", ['kode_billing' => $kodeBilling, 'tagihan' => '25000'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->as($p->pemohon()->value('email'))->post("/permohonan/{$p->id}/bayar", ['bukti' => $this->png()])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->as('bendahara@lab.local')->post("/permohonan/{$p->id}/konfirmasi-bayar", ['keputusan' => 'terima'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    protected function suratTugas(Permohonan $p): void
    {
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/surat-tugas", ['penanda' => 'setelan'])->assertSessionHas('success');
    }

    /** Analis lab mengisi semua parameter lab-nya lalu menyelesaikan. */
    protected function ujiLab(Permohonan $p, string $email, bool $memenuhi = true, string $hasil = 'Negatif'): void
    {
        $u = $this->u($email);
        $isi = $p->parameter()->where('lab_id', $u->lab_id)->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => ['hasil' => $hasil, 'memenuhi' => $memenuhi ? '1' : '0', 'keterangan' => '']])->all();
        $this->as($email)->post("/permohonan/{$p->id}/hasil", ['aksi' => 'selesai', 'hasil' => $isi])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    protected function ujiSemua(Permohonan $p): void
    {
        foreach (['organo@lab.local', 'mikro@lab.local', 'kimia@lab.local'] as $e) {
            $this->ujiLab($p, $e);
        }
        $this->assertSame(Status::MenungguParaf, $p->fresh()->status);
    }

    protected function periksa(Permohonan $p): void
    {
        $this->as('superadmin@lab.local')->post("/permohonan/{$p->id}/periksa", ['cek' => ['identitas' => 1, 'parameter' => 1, 'hasil' => 1]])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    protected function parafSemua(Permohonan $p, array $penyelia = ['penyelia.organo@lab.local', 'penyelia.mikro@lab.local', 'penyelia.kimia@lab.local']): void
    {
        foreach ($penyelia as $e) {
            $this->as($e)->post("/permohonan/{$p->id}/paraf", ['paraf' => $this->paraf()])->assertSessionHasNoErrors()->assertSessionHas('success');
        }
    }

    protected function parafAdmin(Permohonan $p): void
    {
        $this->as('admin@lab.local')->post("/permohonan/{$p->id}/paraf-admin", ['paraf' => $this->paraf()])->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    protected function sahkan(Permohonan $p): void
    {
        $this->as('pj@lab.local')->post("/permohonan/{$p->id}/sahkan", ['jabatan' => 'PJ'])->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    /** Mandiri dari pengajuan sampai LHP selesai. */
    protected function sampaiSelesai(string $email = 'pengguna@lab.local'): Permohonan
    {
        $p = $this->ajukan($email);
        $this->verifikasi($p);
        $this->bayarLunas($p);
        $this->suratTugas($p);
        $this->ujiSemua($p);
        $this->parafSemua($p);
        $this->periksa($p);
        $this->parafAdmin($p);
        $this->sahkan($p);

        return $p->fresh();
    }
}
