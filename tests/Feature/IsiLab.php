<?php

namespace Tests\Feature;

use App\Models\ParameterUji;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

/** Bantuan tes: pengajuan dengan daftar sampel, isian operator per kode Buku Sampel, dan alur sampai LHP terbit. */
trait IsiLab
{
    protected function sebagai(string $email): static
    {
        $u = User::query()->where('email', $email)->firstOrFail();

        return $this->actingAs($u)->withSession(['auth_version' => $u->auth_version, 'login_at' => time()]);
    }

    protected function gambar(): UploadedFile
    {
        $copy = sys_get_temp_dir().'/'.uniqid().'.png';
        file_put_contents($copy, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        return new UploadedFile($copy, 'bukti.png', 'image/png', null, true);
    }

    protected function dataAjukan(array $ubah = []): array
    {
        return array_merge([
            'nama_sampel' => 'Udang Vannamei Beku', 'jumlah_sampel' => 1, 'pelanggan' => 'CV Bahari Demo', 'alamat' => 'Jl. Raya Sedati No. 1, Sidoarjo',
            'jenis_komoditas' => 'Krustasea', 'asal_sampel' => 'Sidoarjo', 'tujuan' => 'DOMESTIK', 'jenis_bayar' => 'MANDIRI',
        ], $ubah);
    }

    /** $ubah['sampel'] (bila ada) bukan isian pengguna jasa: dipakai petugas loket saat verifikasi. */
    protected function ajukanSebagai(string $email, array $ubah = []): Permohonan
    {
        unset($ubah['sampel']);
        $ubah += ['jenis_bayar' => str_starts_with($email, 'eksternal') ? 'EKSTERNAL' : 'MANDIRI'];
        $this->sebagai($email)->post('/permohonan', $this->dataAjukan($ubah))->assertRedirect();

        return Permohonan::query()->latest('id')->firstOrFail();
    }

    /** Rincian sampel yang dicatat petugas loket: ['Air', 'Es Batu'] atau [['jenis_sampel' => ..., 'berat' => ...]]. */
    protected function rincian(array $sampel): array
    {
        return array_map(fn ($s) => is_array($s) ? $s : ['jenis_sampel' => $s], $sampel);
    }

    /** Mandiri sampai sampel masuk lab pertama (verifikasi + rincian sampel, bayar, konfirmasi). */
    protected function sampaiLabMandiri(array $ubah = [], string $billing = '820250000000001', string $ntpn = 'ABCD1234EFGH5678'): Permohonan
    {
        $p = $this->ajukanSebagai('pengguna@lab.local', $ubah);
        $rincian = isset($ubah['sampel']) ? ['sampel' => $this->rincian($ubah['sampel'])] : [];
        $this->sebagai('admin@lab.local')->post("/permohonan/{$p->id}/verifikasi", ['keputusan' => 'valid', 'kode_billing' => $billing] + $rincian)->assertSessionHas('success');
        $this->sebagai('pengguna@lab.local')->post("/permohonan/{$p->id}/bayar", ['ntpn' => $ntpn, 'bukti' => $this->gambar()])->assertSessionHas('success');
        $this->sebagai('admin@lab.local')->post("/permohonan/{$p->id}/konfirmasi-bayar", ['keputusan' => 'terima'])->assertSessionHas('success');

        return $p->fresh();
    }

    /**
     * Operator mengisi lab-nya: [kode Buku Sampel => [ulangan...]] (atau [kode => ['ulangan' => [...], 'melebihi' => 1, ...]]),
     * diterapkan ke semua sampel. Kosong = "Tidak ada uji di lab ini".
     */
    protected function isiLab(Permohonan $p, string $email, array $param = [], array $tambahan = []): TestResponse
    {
        $u = User::query()->where('email', $email)->firstOrFail();
        $isian = [];
        foreach ($p->sampel()->get() as $s) {
            foreach ($param as $kode => $v) {
                [$kel, $kd] = str_contains($kode, ':') ? explode(':', $kode, 2) : [null, $kode];
                $prm = ParameterUji::query()->where('lab_id', $u->lab_id)->where('kode', $kd)
                    ->when($kel, fn ($q) => $q->where('kelompok', $kel))->firstOrFail();
                $d = array_is_list($v) ? ['ulangan' => $v] : $v;
                $isian[$s->id][$prm->id] = $d + ['diuji' => 1, 'persyaratan' => $prm->persyaratan[0] ?? null, 'metode' => $prm->metode, 'keterangan' => $prm->keterangan];
            }
        }

        return $this->sebagai($email)->post("/permohonan/{$p->id}/hasil", $param ? ['isian' => $isian] + $tambahan : ['tanpa_uji' => 1] + $tambahan);
    }

    /** Sensory test produk beku: Keadaan Beku (Lapisan Es, Dehidrasi, Diskolorisasi) + Thawing (Kenampakan, Bau, Tekstur). */
    protected function organoBeku(string $nilai = '8,0'): array
    {
        return ['sub' => [0 => [$nilai, $nilai, $nilai], 1 => [$nilai, $nilai, $nilai]]];
    }

    /** Semua lab: Mikro ALT+E, Kimia F, Organoleptik sensory test beku, Radionuklida tanpa uji => masuk pemeriksaan Administrasi. */
    protected function ujiSemuaLab(Permohonan $p, array $mikro = ['ALT' => ['2,3 x 10^4'], 'E' => ['Negatif/25 g']]): void
    {
        $this->isiLab($p, 'mikro@lab.local', $mikro)->assertRedirect('/beranda');
        $this->isiLab($p, 'kimia@lab.local', ['F' => ['Negatif']])->assertRedirect('/beranda');
        $this->isiLab($p, 'organoleptik@lab.local', ['O' => $this->organoBeku()])->assertRedirect('/beranda');
        $this->isiLab($p, 'radio@lab.local')->assertRedirect('/beranda');
    }

    protected function cekSesuai(Permohonan $p): void
    {
        $this->sebagai('administrasi@lab.local')->post("/permohonan/{$p->id}/cek", ['keputusan' => 'sesuai'])->assertRedirect('/beranda');
    }

    protected function terbitkan(Permohonan $p, array $ubah = []): TestResponse
    {
        return $this->sebagai('admin@lab.local')->post("/permohonan/{$p->id}/validasi-lhu", $ubah + ['ttd' => 0, 'jabatan' => 'PJ']);
    }

    /** Teks document.xml dari isi berkas .docx. */
    protected function teksDocx(string $bin): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tmp, $bin);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp) === true, 'Berkas bukan docx yang valid');
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmp);

        return $xml;
    }
}
