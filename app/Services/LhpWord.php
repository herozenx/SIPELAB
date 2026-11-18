<?php

namespace App\Services;

use App\Enums\JenisBayar;
use App\Models\Lhu;
use App\Models\Permohonan;
use App\Models\Sampel;
use App\Models\Setting;
use App\Support\FormatLhp;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Membuat file Word LHP dari template resources/templates/lhp.docx (salinan LHP asli instansi,
 * dibuat dengan scripts/buat-template-lhp.php). Satu halaman per sampel, isi diambil dari isian operator.
 */
class LhpWord
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Urutan bidang pada tabel LHP asli. */
    private const URUTAN_BIDANG = ['Organoleptik' => 1, 'Biologi' => 2, 'Kimia' => 3, 'Radionuklida' => 4];

    /** Urutan elemen dalam w:rPr menurut skema OOXML (untuk menyisipkan miring/pangkat di tempat yang benar). */
    private const URUTAN_RPR = ['rStyle', 'rFonts', 'b', 'bCs', 'i', 'iCs', 'caps', 'smallCaps', 'strike', 'dstrike', 'outline', 'shadow', 'emboss', 'imprint', 'noProof', 'snapToGrid', 'vanish', 'webHidden', 'color', 'spacing', 'w', 'kern', 'position', 'sz', 'szCs', 'highlight', 'u', 'effect', 'bdr', 'shd', 'fitText', 'vertAlign', 'rtl', 'cs', 'em', 'lang', 'eastAsianLayout', 'specVanish', 'oMath'];

    private DOMDocument $dom;

    private DOMXPath $xp;

    public static function template(): string
    {
        return resource_path('templates/lhp.docx');
    }

    /** Nama berkas unduhan, mis. "LHP SP.M-171126.01 - PT Contoh.docx". */
    public function namaBerkas(Permohonan $p): string
    {
        $kode = str_replace('/', '-', (string) ($p->kode_sampel ?: $p->kode));
        $nama = preg_replace('/[^\pL\pN .,()&-]/u', '', (string) $this->pelanggan($p));

        return trim(mb_substr("LHP {$kode} - {$nama}", 0, 120)).'.docx';
    }

    /** @return string isi berkas .docx */
    public function buat(Permohonan $p): string
    {
        $p->loadMissing(['sampel.hasil', 'lhu', 'pemohon']);
        if ($p->sampel->isEmpty()) {
            throw new RuntimeException('Permohonan belum punya data sampel.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'lhp');
        copy(self::template(), $tmp);
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            throw new RuntimeException('Template LHP tidak bisa dibuka.');
        }

        $this->dom = new DOMDocument();
        $this->dom->preserveWhiteSpace = true;
        $this->dom->loadXML($zip->getFromName('word/document.xml'));
        $this->xp = new DOMXPath($this->dom);
        $this->xp->registerNamespace('w', self::W);

        $body = $this->xp->query('//w:body')->item(0);
        $sect = $this->xp->query('./w:sectPr', $body)->item(0);
        $isi = [];
        foreach (iterator_to_array($body->childNodes) as $n) {
            if ($n !== $sect) {
                $isi[] = $body->removeChild($n);
            }
        }

        foreach ($p->sampel as $i => $s) {
            $wadah = $this->dom->createElementNS(self::W, 'w:wadah');
            foreach ($isi as $n) {
                $wadah->appendChild($n->cloneNode(true));
            }
            $this->isiHalaman($wadah, $p, $s);
            if ($i > 0) {
                $this->halamanBaru($wadah);
            }
            foreach (iterator_to_array($wadah->childNodes) as $n) {
                $body->insertBefore($n, $sect);
            }
        }

        $zip->addFromString('word/document.xml', $this->dom->saveXML());
        $zip->close();
        $bin = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bin;
    }

    public function pelanggan(Permohonan $p): string
    {
        return (string) ($p->pelanggan ?: ($p->pemohon?->instansi ?: $p->pemohon?->nama));
    }

    /** Nomor LHP; sebelum terbit, bagian urut dikosongkan (diisi saat Admin menerbitkan). */
    public function nomor(Permohonan $p): string
    {
        if ($p->lhu?->nomor) {
            return $p->lhu->nomor;
        }
        $tgl = now();

        return strtr(Setting::get(($p->jenis_bayar ?? JenisBayar::Mandiri)->kunciNomor()), ['{NNNN}' => '    ', '{BULAN}' => FormatLhp::romawi($tgl->month), '{TAHUN}' => $tgl->year]);
    }

    /** @return array{nama:string, nip:string, jabatan:string} */
    public function penandatangan(?Lhu $lhu): array
    {
        $def = Setting::penandatangan()[0] ?? ['nama' => '', 'nip' => ''];

        return [
            'nama' => $lhu?->ttd_nama ?: $def['nama'],
            'nip' => $lhu?->ttd_nip ?: $def['nip'],
            'jabatan' => $lhu?->ttd_jabatan ?: Lhu::JABATAN_PJ,
        ];
    }

    /**
     * Baris tabel hasil satu sampel, dikelompokkan per bidang dengan urutan seperti LHP asli.
     *
     * @return array<int, array{no:string, bidang:string, bidang_en:string, baris:array<int, array{param:string, hasil:string, syarat:string, metode:string, ket:string}>}>
     */
    public static function tabel(Sampel $s): array
    {
        $grup = $s->hasil->groupBy('bidang')
            ->sortBy(fn ($rows, $bidang) => self::URUTAN_BIDANG[$bidang] ?? 9);
        $out = [];
        $no = 0;
        foreach ($grup as $bidang => $rows) {
            $out[] = [
                'no' => (++$no).'.',
                'bidang' => $bidang.'/',
                'bidang_en' => (string) $rows->first()->bidang_en,
                'baris' => $rows->sortBy('urutan')->map(fn ($h) => [
                    'param' => (string) $h->nama,
                    'hasil' => FormatLhp::hasil($h->ulangan ?? [], $h->satuan),
                    'syarat' => (string) $h->persyaratan,
                    'metode' => (string) $h->metode,
                    'ket' => (string) $h->keterangan,
                ])->values()->all(),
            ];
        }

        return $out;
    }

    /**
     * Perkiraan tinggi tabel hasil dalam baris teks. Template (LHP asli) berisi 3 baris contoh
     * dengan tinggi minimal 2 baris teks = 6; lebar kolom dalam jumlah karakter per baris.
     */
    public const BARIS_TEMPLATE = 6;

    private const KARAKTER_KOLOM = ['param' => 15, 'hasil' => 14, 'syarat' => 17, 'metode' => 19, 'ket' => 15];

    public static function barisTabel(Sampel $s): int
    {
        $n = 0;
        foreach (self::tabel($s) as $g) {
            foreach ($g['baris'] as $b) {
                $tinggi = 2;
                foreach (self::KARAKTER_KOLOM as $k => $lebar) {
                    $baris = 0;
                    foreach (preg_split('/\R/u', FormatLhp::polos(str_replace('^', '', $b[$k]))) as $seg) {
                        $baris += max(1, (int) ceil(mb_strlen($seg) / $lebar));
                    }
                    $tinggi = max($tinggi, $baris);
                }
                $n += $tinggi;
            }
        }

        return $n;
    }

    /**
     * Tabel lebih panjang dari contoh di template: rapatkan baris kosong (seperti dilakukan petugas
     * pada LHP asli bertabel panjang) supaya satu sampel tetap muat satu halaman.
     * Urutan: jarak catatan -> tanggal, jarak NIP -> catatan kaki, jarak tabel -> catatan; masing-masing disisakan satu.
     */
    private function rapatkan(DOMElement $wadah, int $lebih): void
    {
        if ($lebih <= 0) {
            return;
        }
        $anak = array_values(array_filter(iterator_to_array($wadah->childNodes), fn ($n) => $n instanceof DOMElement));
        $cari = function (callable $cocok) use ($anak): ?int {
            foreach ($anak as $i => $n) {
                if ($cocok($n)) {
                    return $i;
                }
            }

            return null;
        };
        $teks = fn (string $s) => fn ($n) => $n->localName === 'p' && str_contains($n->textContent, $s);
        $kelompok = [
            [$cari($teks('Copy sign')), $cari($teks('{{kota_tanggal}}'))],
            [$cari($teks('{{ttd_nip}}')), $cari($teks('parameter belum akreditasi'))],
            [$cari(fn ($n) => $n->localName === 'tbl'), $cari($teks('Catatan'))],
        ];
        foreach ($kelompok as [$dari, $sampai]) {
            if ($dari === null || $sampai === null || $lebih <= 0) {
                continue;
            }
            $kosong = array_values(array_filter(array_slice($anak, $dari + 1, $sampai - $dari - 1),
                fn ($n) => $n->localName === 'p' && trim($n->textContent) === '' && ! $this->xp->query('.//w:drawing | .//w:pict', $n)->length));
            foreach (array_slice($kosong, 1, $lebih) as $n) {
                $n->parentNode->removeChild($n);
                $lebih--;
            }
        }
    }

    private function isiHalaman(DOMElement $wadah, Permohonan $p, Sampel $s): void
    {
        $this->rapatkan($wadah, self::barisTabel($s) - self::BARIS_TEMPLATE);
        $ttd = $this->penandatangan($p->lhu);
        $tglUji = $p->masuk_lab_at ?? $p->diterima_at ?? $p->created_at;
        $teks = [
            '{{nomor}}' => $this->nomor($p),
            '{{pelanggan}}' => $this->pelanggan($p),
            '{{alamat}}' => (string) ($p->alamat ?: '-'),
            '{{tgl_terima}}' => FormatLhp::tanggal($p->diterima_at ?? $p->created_at),
            '{{jenis_sampel}}' => (string) $s->jenis_sampel,
            '{{kode_sampel}}' => (string) ($s->kode ?: $p->kode_sampel ?: '-'),
            '{{tgl_uji}}' => FormatLhp::tanggal($tglUji),
            '{{kota_tanggal}}' => Setting::get('kota_lhp').', '.FormatLhp::tanggal($p->lhu?->validated_at ?? now()),
            '{{ttd_nama}}' => $ttd['nama'],
            '{{ttd_nip}}' => $ttd['nip'],
        ];
        foreach ($this->xp->query('.//w:t', $wadah) as $t) {
            if (str_contains($t->nodeValue, '{{')) {
                $t->nodeValue = strtr($t->nodeValue, $teks);
            }
        }

        $populasi = ($p->jenis_bayar ?? JenisBayar::Mandiri)->mewakiliPopulasi();
        $coret = [
            '{{S_POP}}' => $populasi ? '0' : '1',
            '{{S_CONTOH}}' => $populasi ? '1' : '0',
            '{{S_KEPALA}}' => $ttd['jabatan'] === Lhu::JABATAN_KEPALA ? '0' : '1',
            '{{S_PJ}}' => $ttd['jabatan'] === Lhu::JABATAN_KEPALA ? '1' : '0',
        ];
        foreach ($this->xp->query('.//w:strike', $wadah) as $st) {
            $v = $st->getAttributeNS(self::W, 'val');
            if (isset($coret[$v])) {
                $st->setAttributeNS(self::W, 'w:val', $coret[$v]);
            }
        }

        $this->isiTabel($wadah, $s);
    }

    private function isiTabel(DOMElement $wadah, Sampel $s): void
    {
        $proto = [];
        foreach ($this->xp->query('.//w:tr', $wadah) as $tr) {
            if (str_contains($tr->textContent, '{{param}}')) {
                $proto[] = $tr;
            }
        }
        if (count($proto) !== 2) {
            throw new RuntimeException('Template LHP rusak: baris contoh tabel tidak lengkap.');
        }
        [$awal, $lanjut] = $proto;
        $tbl = $awal->parentNode;

        foreach (self::tabel($s) as $g) {
            foreach ($g['baris'] as $i => $b) {
                $tr = ($i === 0 ? $awal : $lanjut)->cloneNode(true);
                $nilai = $b + ($i === 0 ? ['no' => $g['no'], 'bidang' => $g['bidang'], 'bidang_en' => $g['bidang_en']] : []);
                foreach (iterator_to_array($this->xp->query('.//w:r', $tr)) as $r) {
                    if (preg_match('/^\{\{(\w+)\}\}$/', trim($r->textContent), $m) && array_key_exists($m[1], $nilai)) {
                        $this->gantiRun($r, $nilai[$m[1]]);
                    }
                }
                $tbl->insertBefore($tr, $awal);
            }
        }
        $tbl->removeChild($awal);
        $tbl->removeChild($lanjut);
        $this->lebarkanParameter($tbl, $s);
    }

    /**
     * Sub-item sensory test tidak boleh terlipat (nilai di kolom Hasil harus sejajar per baris).
     * Seperti pada LHP asli, kolom Parameter dilebarkan dengan mengambil ruang kolom Keterangan lalu Metode;
     * lebar total tabel tetap.
     */
    private function lebarkanParameter(DOMElement $tbl, Sampel $s): void
    {
        $terpanjang = 0;
        foreach (self::tabel($s) as $g) {
            foreach ($g['baris'] as $b) {
                if (substr_count($b['param'], "\n") === 0) {
                    continue; // hanya parameter bersub-item (beberapa baris) yang wajib satu baris per item
                }
                foreach (preg_split('/\R/u', FormatLhp::polos($b['param'])) as $line) {
                    $terpanjang = max($terpanjang, mb_strlen($line));
                }
            }
        }
        if (! $terpanjang) {
            return;
        }
        $grid = iterator_to_array($this->xp->query('./w:tblGrid/w:gridCol', $tbl));
        if (count($grid) !== 7) {
            return;
        }
        $lebar = array_map(fn ($c) => (int) $c->getAttributeNS(self::W, 'w'), $grid);
        // ±92 twip per karakter (Calibri 9 pt) + indentasi & margin sel.
        $perlu = $terpanjang * 92 + 260;
        $tambah = $perlu - $lebar[2];
        if ($tambah <= 0) {
            return;
        }
        // Batas minimal: judul "Keterangan/ Information" & "Persyaratan Mutu/" tetap utuh.
        foreach ([6 => 1200, 5 => 1500, 4 => 1450] as $kolom => $minimal) {
            $ambil = max(0, min($tambah, $lebar[$kolom] - $minimal));
            $lebar[$kolom] -= $ambil;
            $lebar[2] += $ambil;
            $tambah -= $ambil;
        }
        foreach ($grid as $i => $c) {
            $c->setAttributeNS(self::W, 'w:w', (string) $lebar[$i]);
        }
        foreach ($this->xp->query('./w:tr', $tbl) as $tr) {
            $sel = iterator_to_array($this->xp->query('./w:tc', $tr));
            if (count($sel) !== 7) {
                continue;
            }
            foreach ($sel as $i => $tc) {
                $this->xp->query('./w:tcPr/w:tcW', $tc)->item(0)?->setAttributeNS(self::W, 'w:w', (string) $lebar[$i]);
            }
        }
    }

    /** Ganti satu run penanda dengan run berformat: miring, pangkat, dan baris baru. */
    private function gantiRun(DOMElement $r, string $teks): void
    {
        $rpr = $this->xp->query('./w:rPr', $r)->item(0);
        $induk = $r->parentNode;
        foreach (FormatLhp::potong($teks) as $i => $seg) {
            if ($i > 0) {
                $br = $this->dom->createElementNS(self::W, 'w:r');
                $rpr && $br->appendChild($rpr->cloneNode(true));
                $br->appendChild($this->dom->createElementNS(self::W, 'w:br'));
                $induk->insertBefore($br, $r);
            }
            foreach ($seg as [$isi, $miring, $pangkat]) {
                $baru = $this->dom->createElementNS(self::W, 'w:r');
                $p = $rpr ? $rpr->cloneNode(true) : $this->dom->createElementNS(self::W, 'w:rPr');
                if ($miring) {
                    $this->sisipRpr($p, 'i');
                    $this->sisipRpr($p, 'iCs');
                }
                if ($pangkat) {
                    $this->sisipRpr($p, 'vertAlign', 'superscript');
                }
                $baru->appendChild($p);
                $t = $this->dom->createElementNS(self::W, 'w:t');
                $t->appendChild($this->dom->createTextNode($isi));
                $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
                $baru->appendChild($t);
                $induk->insertBefore($baru, $r);
            }
        }
        $induk->removeChild($r);
    }

    private function sisipRpr(DOMElement $rpr, string $nama, ?string $val = null): void
    {
        foreach ($this->xp->query('./w:'.$nama, $rpr) as $lama) {
            $rpr->removeChild($lama);
        }
        $el = $this->dom->createElementNS(self::W, 'w:'.$nama);
        if ($val !== null) {
            $el->setAttributeNS(self::W, 'w:val', $val);
        }
        $pos = array_search($nama, self::URUTAN_RPR, true);
        foreach ($rpr->childNodes as $c) {
            if ($c instanceof DOMElement && ($cp = array_search($c->localName, self::URUTAN_RPR, true)) !== false && $cp > $pos) {
                $rpr->insertBefore($el, $c);

                return;
            }
        }
        $rpr->appendChild($el);
    }

    /** Sampel kedua dst. mulai di halaman baru (tanpa menambah baris kosong). */
    private function halamanBaru(DOMElement $wadah): void
    {
        $p = $this->xp->query('./w:p', $wadah)->item(0);
        if (! $p) {
            return;
        }
        $ppr = $this->xp->query('./w:pPr', $p)->item(0);
        if (! $ppr) {
            $ppr = $this->dom->createElementNS(self::W, 'w:pPr');
            $p->insertBefore($ppr, $p->firstChild);
        }
        $pb = $this->dom->createElementNS(self::W, 'w:pageBreakBefore');
        // pageBreakBefore berada setelah pStyle/keepNext/keepLines dalam w:pPr.
        $sebelum = null;
        foreach ($ppr->childNodes as $c) {
            if ($c instanceof DOMElement && ! in_array($c->localName, ['pStyle', 'keepNext', 'keepLines'], true)) {
                $sebelum = $c;
                break;
            }
        }
        $ppr->insertBefore($pb, $sebelum);
    }
}
