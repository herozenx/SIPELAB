<?php

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\HasilParameter;
use App\Models\Lab;
use App\Models\Lhu;
use App\Models\ParameterUji;
use App\Models\Permohonan;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Isian operator seperti Buku Sampel, file LHP Word format instansi, dan pemeriksaan Administrasi. */
class BukuSampelLhpTest extends TestCase
{
    use IsiLab;
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-11-17 09:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function strikeRun(string $xml, string $kata): ?string
    {
        // Nilai w:strike pada run yang memuat kata tersebut.
        if (preg_match('#<w:r[ >](?:(?!</w:r>).)*?<w:strike w:val="([01])"/>(?:(?!</w:r>).)*?<w:t[^>]*>[^<]*'.preg_quote($kata, '#').'#s', $xml, $m)) {
            return $m[1];
        }

        return null;
    }

    public function test_kode_sampel_urut_harian_semua_layanan_dan_huruf_per_sampel(): void
    {
        $a = $this->sampaiLabMandiri();
        $this->assertSame('SP.M/171126.01', $a->kode_sampel);
        $this->assertSame(['SP.M/171126.01'], $a->sampel()->pluck('kode')->all());

        // Hari yang sama, layanan lain: urutan berlanjut (.02). Pengguna jasa hanya mengajukan produk;
        // petugas loket memecahnya jadi dua sampel => huruf A, B, dan tagihan mengikuti jumlah sampel.
        $b = $this->ajukanSebagai('eksternal@lab.local', ['nama_sampel' => 'Air dan es pabrik']);
        $this->assertSame(['Air dan es pabrik'], $b->sampel()->pluck('jenis_sampel')->all());
        $this->sebagai('admin@lab.local')->get("/permohonan/{$b->id}")->assertOk()->assertSee('Rincian sampel')->assertSee('Tambah sampel');
        $this->sebagai('admin@lab.local')->post("/permohonan/{$b->id}/verifikasi", [
            'keputusan' => 'valid', 'jenis_bayar' => 'EKSTERNAL', 'sampel' => [['jenis_sampel' => ' ']],
        ])->assertSessionHasErrors('sampel');
        $this->sebagai('admin@lab.local')->post("/permohonan/{$b->id}/verifikasi", [
            'keputusan' => 'valid', 'jenis_bayar' => 'EKSTERNAL', 'no_surat' => '849', 'subkontrak' => ['Angler', 'LPPMHP'],
            'sampel' => [['jenis_sampel' => 'Air', 'berat' => ''], ['jenis_sampel' => 'Es Batu', 'berat' => '1 kg'], ['jenis_sampel' => '']],
        ])->assertSessionHas('success');
        $b->refresh();
        $this->assertSame('SP.PE/171126.02', $b->kode_sampel);
        $this->assertSame(['Air', 'Es Batu'], $b->sampel()->pluck('jenis_sampel')->all());
        $this->assertSame(['SP.PE/171126.02A', 'SP.PE/171126.02B'], $b->sampel()->pluck('kode')->all());
        $this->assertSame(2, $b->jumlah_sampel);
        $this->assertSame('Angler, LPPMHP', $b->subkontrak); // kolom EKSTERNAL Buku Sampel
        $this->assertSame(2 * 350000 + 10000, $b->tagihan);
        // Operator lab tidak melihat tagihan/pembayaran, termasuk di teks riwayat (buang dulu flash milik admin).
        $this->sebagai('mikro@lab.local')->get('/beranda');
        $this->sebagai('mikro@lab.local')->get("/permohonan/{$b->id}")->assertOk()->assertDontSee('Tagihan')->assertDontSee('Rp710.000')->assertSee('kode sampel SP.PE/171126.02');
        $this->assertSame('1 kg', $b->sampel[1]->berat);
        $this->assertSame('849', $b->no_surat);

        // Besok kembali ke .01.
        Carbon::setTestNow(Carbon::parse('2026-11-18 08:00', 'Asia/Jakarta'));
        $c = $this->ajukanSebagai('pengguna@lab.local', ['jenis_bayar' => 'SURVEILEN']);
        $this->sebagai('admin@lab.local')->post("/permohonan/{$c->id}/verifikasi", ['keputusan' => 'valid', 'jenis_bayar' => 'SURVEILEN', 'kode_billing' => '820260000000055']);
        $this->assertSame('SP.S/181126.01', $c->fresh()->kode_sampel);
    }

    public function test_operator_mencentang_parameter_per_sampel_dengan_ulangan(): void
    {
        $p = $this->sampaiLabMandiri(['sampel' => ['Air', 'Es Batu']]);
        $this->sebagai('mikro@lab.local')->get("/permohonan/{$p->id}")->assertOk()
            ->assertSee('BAKTERIOLOGI')->assertSee('MEMBRAN FILTER')->assertSee('SP.M/171126.01A')
            // Ringkas: tombol cepat hanya hasil berupa kata; angka diketik; tanpa kolom metode/keterangan.
            ->assertSee('data-isi-semua="Negatif/25 g"', false)->assertDontSee('data-isi-semua="0 kol/100 ml"', false)
            ->assertDontSee('Metode acuan')->assertDontSee('name="isian['.$p->sampel[0]->id.']['.ParameterUji::query()->where('kode', 'ALT')->value('id').'][metode]"', false)
            // Persyaratan bisa dipilih hanya bila katalog punya beberapa opsi (ALT: beku vs kaleng).
            ->assertSee('Maks 1,0 x 10⁵ kol/g')->assertSee('0 kol/g');

        // Form hanya mengirim hasil (+ persyaratan pilihan ALT); metode & keterangan otomatis dari katalog.
        $ec = ParameterUji::query()->where('kelompok', 'MEMBRAN FILTER')->where('kode', 'EC')->firstOrFail();
        $altP = ParameterUji::query()->where('kode', 'ALT')->firstOrFail();
        $isian = [];
        foreach ($p->sampel as $s) {
            $isian[$s->id][$ec->id] = ['diuji' => 1, 'ulangan' => ['0']];
            $isian[$s->id][$altP->id] = ['diuji' => 1, 'ulangan' => ['2,3 x 10^4', '6,0 x 10^4', '9,6 x 10^4'], 'persyaratan' => '0 kol/g'];
        }
        $this->sebagai('mikro@lab.local')->post("/permohonan/{$p->id}/hasil", ['isian' => $isian])->assertRedirect('/beranda');
        $alt = HasilParameter::query()->where('nama', 'Angka Lempeng Total (ALT) Aerob')->firstOrFail();
        $this->assertSame(['2,3 x 10^4', '6,0 x 10^4', '9,6 x 10^4'], $alt->ulangan);
        $this->assertSame('0 kol/g', $alt->persyaratan);
        $this->assertSame('SNI 2332.3-2015', $alt->metode);
        $ecH = HasilParameter::query()->where('parameter_id', $ec->id)->firstOrFail();
        $this->assertSame('0 kol/100 ml', $ecH->persyaratan);
        $this->assertSame('ISO 9308-1:2014 / Amd.1:2016(E)', $ecH->metode);
        $this->assertSame(4, HasilParameter::query()->count()); // 2 parameter x 2 sampel
        $this->assertSame(Status::DiLab, $p->fresh()->status);
        $this->assertSame('Kimia', $p->fresh()->labSaatIni->nama);

        // Hasil wajib diisi bila parameter dicentang; parameter lab lain ditolak.
        $prmF = ParameterUji::query()->where('kode', 'F')->firstOrFail();
        $sid = $p->sampel[0]->id;
        $this->sebagai('kimia@lab.local')->post("/permohonan/{$p->id}/hasil", ['isian' => [$sid => [$prmF->id => ['diuji' => 1, 'ulangan' => ['']]]]])->assertSessionHas('error');
        $prmAlt = ParameterUji::query()->where('kode', 'ALT')->firstOrFail();
        $this->sebagai('kimia@lab.local')->post("/permohonan/{$p->id}/hasil", ['isian' => [$sid => [$prmAlt->id => ['diuji' => 1, 'ulangan' => ['1']]]]])->assertSessionHas('error');
        $this->sebagai('kimia@lab.local')->post("/permohonan/{$p->id}/hasil", [])->assertSessionHas('error'); // tidak centang apa pun
    }

    public function test_admin_lab_kepala_tim_bisa_mengisi_di_lab_mana_pun(): void
    {
        $p = $this->sampaiLabMandiri();
        // Sampel di Mikrobiologi: Admin Lab bisa menangani & mengisi, operator lab lain tetap tidak.
        $this->sebagai('admin@lab.local')->get("/permohonan/{$p->id}")->assertOk()
            ->assertSee('Isian Lab Mikrobiologi')->assertSee('Sebagai kepala tim operator')->assertSee('BAKTERIOLOGI');
        $this->sebagai('admin@lab.local')->post("/permohonan/{$p->id}/tangani")->assertSessionHas('success');
        $this->isiLab($p, 'kimia@lab.local', ['F' => ['Negatif']])->assertNotFound();

        // isiLab memakai katalog lab akun; Admin Lab tidak punya lab, jadi kirim isian manual untuk lab Mikrobiologi.
        $alt = \App\Models\ParameterUji::query()->where('kode', 'ALT')->firstOrFail();
        $sid = $p->sampel()->value('id');
        $this->sebagai('admin@lab.local')->post("/permohonan/{$p->id}/hasil", ['isian' => [$sid => [$alt->id => [
            'diuji' => 1, 'ulangan' => ['2,3 x 10^4'], 'persyaratan' => 'Maks 1,0 x 10^5 kol/g', 'metode' => 'SNI 2332.3-2015',
        ]]]])->assertRedirect('/beranda');
        $this->assertSame('Kimia', $p->fresh()->labSaatIni->nama);
        $this->assertSame('Admin Lab', HasilParameter::query()->firstOrFail()->petugas->nama);

        // Lab berikutnya juga bisa dikerjakan Admin Lab (mis. operator Kimia berhalangan).
        $this->sebagai('admin@lab.local')->post("/permohonan/{$p->id}/hasil", ['tanpa_uji' => 1])->assertRedirect('/beranda');
        $this->assertSame('Organoleptik', $p->fresh()->labSaatIni->nama);

        // Administrasi tetap tidak bisa mengisi hasil lab.
        $this->sebagai('administrasi@lab.local')->post("/permohonan/{$p->id}/hasil", ['tanpa_uji' => 1])->assertForbidden();
    }

    public function test_sensory_test_diisi_per_sub_item_seperti_lhps(): void
    {
        $p = $this->sampaiLabMandiri();
        $this->isiLab($p, 'mikro@lab.local')->assertRedirect('/beranda');
        $this->isiLab($p, 'kimia@lab.local')->assertRedirect('/beranda');
        $this->sebagai('organoleptik@lab.local')->get("/permohonan/{$p->id}")->assertOk()
            ->assertSee('1. Keadaan Beku')->assertSee('2. Thawing')->assertSee('3. Keadaan Segar')->assertSee('4. Produk Kering')->assertSee('5. Produk Kaleng')
            ->assertSee('Kenampakan Insang')->assertSee('Konsistensi')
            ->assertDontSee('data-isi-semua="7,0"', false); // nilai angka diketik manual

        // Ikan segar: hanya sebagian sub-item dinilai; yang kosong tidak dicetak & nomor tetap urut.
        $this->isiLab($p, 'organoleptik@lab.local', ['O' => ['sub' => [2 => [0 => '8,0', 1 => '', 4 => '7,0']]]])->assertRedirect('/beranda');
        $h = HasilParameter::query()->where('bidang', 'Organoleptik')->firstOrFail();
        $this->assertSame("Sensory test :\nA. Keadaan Segar\n   1. Kenampakan Mata\n   2. Bau", $h->nama);
        $this->assertSame(['', '', '8,0', '7,0'], $h->ulangan);
        $this->assertSame([2 => [0 => '8,0', 4 => '7,0']], $h->rincian);

        // Tidak mengisi satu pun nilai => ditolak.
        $p2 = $this->sampaiLabMandiri([], '820250000000003', 'ABCD1234EFGH0003');
        $this->isiLab($p2, 'mikro@lab.local');
        $this->isiLab($p2, 'kimia@lab.local');
        $this->isiLab($p2, 'organoleptik@lab.local', ['O' => ['sub' => [0 => ['', '', '']]]])->assertSessionHas('error');
    }

    public function test_semua_lab_tanpa_uji_ditolak_di_lab_terakhir(): void
    {
        $p = $this->sampaiLabMandiri();
        foreach (['mikro', 'kimia', 'organoleptik'] as $l) {
            $this->isiLab($p, "$l@lab.local")->assertRedirect('/beranda');
        }
        $this->isiLab($p, 'radio@lab.local')->assertSessionHas('error');
        $this->assertSame(Status::DiLab, $p->fresh()->status);
    }

    public function test_administrasi_mengembalikan_ke_lab_lalu_langsung_kembali_ke_administrasi(): void
    {
        $p = $this->sampaiLabMandiri();
        $this->ujiSemuaLab($p);
        $mikro = Lab::query()->where('nama', 'Mikrobiologi')->value('id');

        $this->sebagai('administrasi@lab.local')->get('/beranda')->assertOk()->assertSee($p->kode_sampel);
        $this->sebagai('administrasi@lab.local')->get("/permohonan/{$p->id}")->assertOk()
            ->assertSee('Pemeriksaan administrasi')->assertSee('Angka Lempeng Total')->assertSee('Petugas Mikrobiologi')
            ->assertSee('820250000000001')->assertSee('Rp360.000'); // Administrator juga mengurus pembayaran
        $this->sebagai('administrasi@lab.local')->get("/permohonan/{$p->id}/pratinjau-lhp")->assertOk()->assertSee('This is certify that');

        $this->sebagai('administrasi@lab.local')->post("/permohonan/{$p->id}/cek", ['keputusan' => 'kembalikan', 'lab_id' => $mikro])->assertSessionHasErrors('catatan');
        $this->sebagai('administrasi@lab.local')->post("/permohonan/{$p->id}/cek", ['keputusan' => 'kembalikan', 'lab_id' => $mikro, 'catatan' => 'ALT tertulis 10^4, lembar kerja 10^5'])->assertRedirect('/beranda');
        $p->refresh();
        $this->assertSame(Status::DiLab, $p->status);
        $this->assertSame($mikro, $p->current_lab_id);
        $this->assertTrue($p->dikembalikan);

        $this->sebagai('mikro@lab.local')->get("/permohonan/{$p->id}")->assertOk()
            ->assertSee('Dikembalikan Administrasi')->assertSee('ALT tertulis 10^4, lembar kerja 10^5')->assertSee('value="2,3 x 10^4"', false);
        $this->isiLab($p, 'mikro@lab.local', ['ALT' => ['2,3 x 10^5'], 'E' => ['Negatif/25 g']])->assertRedirect('/beranda');

        $p->refresh();
        $this->assertSame(Status::MenungguCekAdministrasi, $p->status); // tidak lewat Kimia dst. lagi
        $this->assertFalse($p->dikembalikan);
        $this->assertSame(['2,3 x 10^5'], HasilParameter::query()->where('nama', 'Angka Lempeng Total (ALT) Aerob')->firstOrFail()->ulangan);
        $this->assertTrue(HasilParameter::query()->where('nama', 'Formalin')->exists()); // isian lab lain tetap

        $this->cekSesuai($p);
        $this->assertSame(Status::MenungguValidasi, $p->fresh()->status);
        $this->assertSame('Administrator', Lhu::query()->where('permohonan_id', $p->id)->first()->pemeriksa->nama);
    }

    public function test_hak_akses_administrasi_dan_unduh_word(): void
    {
        $p = $this->ajukanSebagai('pengguna@lab.local');
        // Administrator juga memverifikasi pengajuan, jadi permohonan baru sudah terlihat.
        $this->sebagai('administrasi@lab.local')->get("/permohonan/{$p->id}")->assertOk()->assertSee('Verifikasi sampel fisik');

        $p = $this->sampaiLabMandiri([], '820250000000002', 'ABCD1234EFGH0002');
        $this->ujiSemuaLab($p);
        $this->sebagai('administrasi@lab.local')->post("/permohonan/{$p->id}/validasi-lhu", ['ttd' => 0, 'jabatan' => 'PJ'])->assertForbidden();
        $this->sebagai('admin@lab.local')->post("/permohonan/{$p->id}/cek", ['keputusan' => 'sesuai'])->assertForbidden();
        $this->sebagai('administrasi@lab.local')->get("/permohonan/{$p->id}/lhp.docx")->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->sebagai('mikro@lab.local')->get("/permohonan/{$p->id}/lhp.docx")->assertNotFound();
        $this->sebagai('pengguna@lab.local')->get("/permohonan/{$p->id}/lhp.docx")->assertNotFound();
        $this->sebagai('administrasi@lab.local')->get('/admin/parameter')->assertForbidden();
    }

    public function test_file_word_mengikuti_format_lhp_instansi(): void
    {
        $p = $this->sampaiLabMandiri(['sampel' => ['Air', 'Es Batu'], 'alamat' => 'Raya Boboh No.52, Menganti, Gresik']);
        $this->ujiSemuaLab($p, ['MEMBRAN FILTER:EC' => ['0 kol/100 ml'], 'MEMBRAN FILTER:CL' => ['0'], 'ALT' => ['2,3 x 10^4', '6,0 x 10^4', '9,6 x 10^4']]);
        $this->cekSesuai($p);
        $this->terbitkan($p, ['ttd' => 1, 'jabatan' => 'PJ'])->assertSessionHas('success');
        $lhu = Lhu::query()->where('permohonan_id', $p->id)->firstOrFail();

        $res = $this->sebagai('pengguna@lab.local')->get("/permohonan/{$p->id}/lhp.docx")->assertOk();
        $this->assertStringContainsString('LHP SP.M-171126.01 - CV Bahari Demo.docx', rawurldecode((string) $res->headers->get('Content-Disposition')));
        $xml = $this->teksDocx($res->getContent());
        $teks = html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml)));

        // Isian dari permohonan & operator.
        foreach (['CV Bahari Demo', 'Raya Boboh No.52, Menganti, Gresik', '17 November 2026', 'SP.M/171126.01A', 'SP.M/171126.01B',
            'Air', 'Es Batu', $lhu->nomor, 'Escherichia coli', 'Coliform', '0 kol/100 ml', 'ISO 9308-1:2014 / Amd.1:2016(E)',
            'Angka Lempeng Total (ALT) Aerob', 'Formalin', 'IKM/OK/LP/SUB II/6', 'Sensory test', 'Minimal 7,0', 'SNI 2346:2015',
            'Sidoarjo, 17 November 2026', 'Zaki Muhammad Wijaya, S.Pi, M.Si', 'NIP. 19830813 200912 1 001'] as $harus) {
            $this->assertStringContainsString($harus, $teks, "LHP harus memuat: {$harus}");
        }
        // Teks tetap LHP asli tidak berubah.
        foreach (['Menyatakan bahwa', 'This is certify that', 'Pelanggan', 'Customer', 'Tanggal Penerimaan', 'Persyaratan Mutu', 'Reference Method',
            'Laporan hasil uji ini terdiri dari 1 (satu) lembar asli (Stempel Asli)', 'Coret yang tidak sesuai', 'FL/OK/LP/SUB II/8.6.d'] as $tetap) {
            $this->assertStringContainsString($tetap, $teks, "Teks LHP asli hilang: {$tetap}");
        }
        // Sensory test per sub-item seperti LHP asli (produk beku: Keadaan Beku + Thawing).
        foreach (['Sensory test :', 'A. Keadaan Beku', '1. Lapisan Es', '2. Dehidrasi', '3. Diskolorisasi', 'B. Thawing', '1. Kenampakan', '2. Bau', '3. Tekstur'] as $baris) {
            $this->assertStringContainsString($baris, $teks, "Sub-item sensory test hilang: {$baris}");
        }
        $this->assertStringNotContainsString('Keadaan Segar', $teks); // kelompok yang tidak diisi tidak dicetak

        // Baris Pelanggan berisi nama pelanggan, baris Alamat berisi alamat (bukan tertukar).
        $this->assertMatchesRegularExpression('#Customer[^\n]*:\s*CV Bahari Demo\s*\n#u', $teks);
        $this->assertMatchesRegularExpression('#Address[^\n]*:\s*Raya Boboh No\.52, Menganti, Gresik\s*\n#u', $teks);
        $this->assertStringNotContainsString('{{', $xml, 'Masih ada penanda template yang belum terisi');
        $this->assertStringNotContainsString('Seaweed', $xml, 'Isi LHP contoh ikut terbawa');
        $this->assertStringNotContainsString('Lulus', $teks);

        // Angka 0 hasil ketik operator + satuan otomatis; pangkat jadi superscript; nama spesies miring.
        // E. coli & Coliform x 2 sampel: hasil ("0" diketik -> "0 kol/100 ml") + persyaratan = 8.
        $this->assertSame(8, substr_count($teks, '0 kol/100 ml'));
        $this->assertMatchesRegularExpression('#<w:vertAlign w:val="superscript"/>(?:(?!</w:r>).)*<w:t[^>]*>4</w:t>#s', $xml);
        $this->assertMatchesRegularExpression('#<w:i/>(?:(?!</w:r>).)*<w:t[^>]*>Escherichia coli</w:t>#s', $xml);

        // Dua sampel => dua halaman; tabel tanpa baris contoh.
        $this->assertSame(1, substr_count($xml, '<w:pageBreakBefore/>'));
        $this->assertSame(2, substr_count($teks, 'This is certify that'));

        // Mandiri: "mewakili populasi" dicoret; jabatan PJ => "Kepala UPT" dicoret.
        $this->assertSame('1', $this->strikeRun($xml, 'mewakili'));
        $this->assertSame('0', $this->strikeRun($xml, 'hanya berlaku'));
        $this->assertSame('1', $this->strikeRun($xml, 'Kepala'));
        $this->assertSame('0', $this->strikeRun($xml, 'Penanggung jawab'));
    }

    public function test_surveilen_mewakili_populasi_dan_jabatan_kepala_upt(): void
    {
        $p = $this->sampaiLabMandiri(['jenis_bayar' => 'SURVEILEN']);
        $this->ujiSemuaLab($p);
        $this->cekSesuai($p);
        $this->terbitkan($p, ['ttd' => 0, 'jabatan' => 'KEPALA']);
        $xml = $this->teksDocx($this->sebagai('admin@lab.local')->get("/permohonan/{$p->id}/lhp.docx")->getContent());
        $this->assertSame('0', $this->strikeRun($xml, 'mewakili'));
        $this->assertSame('1', $this->strikeRun($xml, 'hanya berlaku'));
        $this->assertSame('0', $this->strikeRun($xml, 'Kepala'));
        $this->assertSame('1', $this->strikeRun($xml, 'Penanggung jawab'));
    }

    public function test_sampel_internal_monitoring_tanpa_tagihan(): void
    {
        $this->sebagai('admin@lab.local')->get('/sampel-internal')->assertOk()->assertSee('Monitoring')->assertSee('Uji Banding');
        $this->sebagai('pengguna@lab.local')->get('/sampel-internal')->assertForbidden();
        $this->sebagai('admin@lab.local')->post('/sampel-internal', [
            'jenis_bayar' => 'MONITORING', 'pelanggan' => 'Monitoring Pasar Pabean', 'alamat' => 'Jl. Songoyudan, Nyamplungan, Pabean Cantikan, Surabaya',
            'sampel' => [['jenis_sampel' => 'Cumi-cumi'], ['jenis_sampel' => 'Ikan Tongkol', 'berat' => '1 kg']],
        ])->assertRedirect();
        $p = Permohonan::query()->latest('id')->firstOrFail();
        $this->assertSame('SP.MN/171126.01', $p->kode_sampel);
        $this->assertSame(['SP.MN/171126.01A', 'SP.MN/171126.01B'], $p->sampel()->pluck('kode')->all());
        $this->assertSame(Status::DiLab, $p->status);
        $this->assertSame(0, $p->tagihan);

        // Pengguna jasa tidak bisa memilih layanan internal.
        $this->sebagai('pengguna@lab.local')->post('/permohonan', $this->dataAjukan(['jenis_bayar' => 'MONITORING']))->assertSessionHasErrors('jenis_bayar');

        $this->ujiSemuaLab($p);
        $this->cekSesuai($p);
        $this->terbitkan($p)->assertSessionHas('success');
        $this->assertSame(Status::Selesai, $p->fresh()->status);
        $teks = strip_tags($this->teksDocx($this->sebagai('admin@lab.local')->get("/permohonan/{$p->id}/lhp.docx")->getContent()));
        $this->assertStringContainsString('Monitoring Pasar Pabean', $teks);
        $this->assertStringContainsString('Cumi-cumi', $teks);
    }

    public function test_format_nomor_per_layanan_dan_urut_berlanjut(): void
    {
        $set = ['nama_instansi' => 'Balai Uji', 'alamat_instansi' => 'Sidoarjo', 'biaya_admin' => 10000, 'norma_total_hari' => 6,
            'kota_lhp' => 'Surabaya', 'penandatangan' => "Dyah Setyawati, S.Pi., MP.|19780207 200212 2 001"];
        foreach (\App\Enums\JenisBayar::cases() as $j) {
            $set[$j->kunciNomor()] = Setting::DEFAULTS[$j->kunciNomor()];
        }
        $this->sebagai('superadmin@lab.local')->put('/admin/pengaturan', ['nomor_lhp_eksternal' => 'B.TANPA-URUT'] + $set)->assertSessionHasErrors('nomor_lhp_eksternal');
        $this->sebagai('superadmin@lab.local')->put('/admin/pengaturan', ['nomor_lhp_eksternal' => 'B.{NNNN}/BPPMHKP.SBY.II/TU.330/{BULAN}/{TAHUN}'] + $set)->assertSessionHas('success');

        $a = $this->sampaiLabMandiri();
        $this->ujiSemuaLab($a);
        $this->cekSesuai($a);
        $this->terbitkan($a);
        $this->assertSame('0001/LAB/BPPMHKP.SBY.II/KI.310/XI/2026', $a->fresh()->lhu->nomor);

        $b = $this->ajukanSebagai('eksternal@lab.local');
        $this->sebagai('admin@lab.local')->post("/permohonan/{$b->id}/verifikasi", ['keputusan' => 'valid', 'jenis_bayar' => 'EKSTERNAL']);
        $this->ujiSemuaLab($b);
        $this->cekSesuai($b);
        $this->terbitkan($b, ['kode_billing' => '820260000000010'])->assertSessionHas('success');
        $this->assertSame('B.0002/BPPMHKP.SBY.II/TU.330/XI/2026', $b->fresh()->lhu->nomor);
        $this->assertStringContainsString('Surabaya, 17 November 2026', strip_tags($this->teksDocx($this->sebagai('admin@lab.local')->get("/permohonan/{$b->id}/lhp.docx")->getContent())));
    }

    public function test_katalog_parameter_dikelola_superadmin(): void
    {
        $this->sebagai('superadmin@lab.local')->get('/admin/parameter')->assertOk()
            ->assertSee('Angka Lempeng Total (ALT) Aerob')->assertSee('SNI 2332.3-2015')->assertSee('Belum diisi');
        $aoz = ParameterUji::query()->where('kode', 'AOZ')->firstOrFail();
        $this->sebagai('superadmin@lab.local')->put("/admin/parameter/{$aoz->id}", [
            'lab_id' => $aoz->lab_id, 'kelompok' => 'KIMIA', 'kode' => 'AOZ', 'bidang' => 'Kimia', 'bidang_en' => 'Chemical', 'nama' => 'AOZ',
            'satuan' => 'µg/kg', 'pilihan' => "ND", 'persyaratan' => "RPA 0,5 µg/kg\nND", 'metode' => 'IKM/OK/LP/SUB II/13', 'ulangan' => 1, 'urutan' => 15, 'aktif' => 1,
        ])->assertRedirect('/admin/parameter');
        $this->assertSame(['RPA 0,5 µg/kg', 'ND'], $aoz->fresh()->persyaratan);
        $this->sebagai('admin@lab.local')->get('/admin/parameter')->assertForbidden();
    }
}
