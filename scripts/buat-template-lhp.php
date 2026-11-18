<?php

/**
 * Membuat resources/templates/lhp.docx dari satu file LHP asli instansi (.docx).
 * Hanya isian yang diganti penanda {{...}}; tata letak, font, tab, teks bilingual, catatan,
 * dan kode formulir dibiarkan persis seperti aslinya.
 *
 *   php scripts/buat-template-lhp.php "C:\...\LHP 2026\MANDIRI\PT. SEAWEED INDONESIA TRADING.docx"
 *
 * Penanda yang dihasilkan (diisi App\Services\LhpWord):
 *   {{nomor}} {{pelanggan}} {{alamat}} {{tgl_terima}} {{jenis_sampel}} {{kode_sampel}} {{tgl_uji}}
 *   {{kota_tanggal}} {{ttd_nama}} {{ttd_nip}}
 *   baris tabel: {{no}} {{bidang}} {{bidang_en}} {{param}} {{hasil}} {{syarat}} {{metode}} {{ket}}
 *   coretan    : <w:strike w:val="{{S_POP}}|{{S_CONTOH}}|{{S_KEPALA}}|{{S_PJ}}"/>
 */

const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

$src = $argv[1] ?? exit("Pakai: php scripts/buat-template-lhp.php <LHP asli .docx>\n");
$dst = __DIR__.'/../resources/templates/lhp.docx';
@mkdir(dirname($dst), 0777, true);
copy($src, $dst) || exit("Gagal menyalin $src\n");

$zip = new ZipArchive();
$zip->open($dst) === true || exit("Bukan berkas docx\n");
$dom = new DOMDocument();
$dom->preserveWhiteSpace = true;
$dom->loadXML($zip->getFromName('word/document.xml'));
$xp = new DOMXPath($dom);
$xp->registerNamespace('w', W);

$teks = fn (DOMNode $n) => implode('', array_map(fn ($t) => $t->textContent, iterator_to_array($xp->query('.//w:t', $n))));
$runs = fn (DOMNode $p) => iterator_to_array($xp->query('./w:r', $p));

/** Run baru berisi satu teks, memakai salinan rPr dari $contoh. */
$runBaru = function (?DOMElement $contoh, string $isi) use ($dom, $xp): DOMElement {
    $r = $dom->createElementNS(W, 'w:r');
    $rpr = $contoh ? $xp->query('./w:rPr', $contoh)->item(0) : null;
    if ($rpr) {
        $r->appendChild($rpr->cloneNode(true));
    }
    $t = $dom->createElementNS(W, 'w:t', htmlspecialchars($isi, ENT_XML1));
    $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
    $r->appendChild($t);

    return $r;
};

/** Paragraf pertama (di luar tabel) yang teksnya cocok. */
$paragraf = function (string $regex) use ($xp, $teks): DOMElement {
    foreach ($xp->query('//w:body/w:p') as $p) {
        if (preg_match($regex, $teks($p))) {
            return $p;
        }
    }
    exit("Paragraf $regex tidak ditemukan\n");
};

/** Ganti semua teks setelah titik dua pertama dengan penanda (format diambil dari run isian pertama). */
$isiSetelahTitikDua = function (DOMElement $p, string $penanda) use ($runs, $xp, $runBaru) {
    $lewat = false;
    $contoh = null;
    foreach ($runs($p) as $r) {
        $t = $xp->query('./w:t', $r)->item(0);
        if ($lewat) {
            if ($t && trim($t->textContent) !== '' && ! $contoh) {
                $contoh = $r;
            }
            $r->parentNode->removeChild($r);
            continue;
        }
        if ($t && ($pos = strpos($t->textContent, ':')) !== false) {
            $sisa = trim(substr($t->textContent, $pos + 1));
            $t->textContent = substr($t->textContent, 0, $pos + 1).' ';
            $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $lewat = true;
            if ($sisa !== '') {
                $contoh = $r;
            }
        }
    }
    $lewat || exit("Titik dua tidak ditemukan pada paragraf {$penanda}\n");
    $p->appendChild($runBaru($contoh, $penanda));
};

/** Ganti seluruh isi paragraf dengan satu run penanda (format run pertama). */
$isiParagraf = function (DOMElement $p, string $penanda, ?DOMElement $contoh = null) use ($runs, $runBaru) {
    $rs = $runs($p);
    $contoh ??= $rs[0] ?? null;
    $baru = $runBaru($contoh, $penanda);
    foreach ($rs as $r) {
        $r->parentNode->removeChild($r);
    }
    $p->appendChild($baru);

    return $baru;
};

/** Pasang/ganti <w:strike> pada rPr dengan urutan elemen sesuai skema OOXML. */
$pasangCoret = function (DOMElement $r, string $penanda) use ($dom, $xp) {
    $rpr = $xp->query('./w:rPr', $r)->item(0);
    if (! $rpr) {
        $rpr = $dom->createElementNS(W, 'w:rPr');
        $r->insertBefore($rpr, $r->firstChild);
    }
    foreach ($xp->query('./w:strike', $rpr) as $s) {
        $rpr->removeChild($s);
    }
    $s = $dom->createElementNS(W, 'w:strike');
    $s->setAttributeNS(W, 'w:val', $penanda);
    $setelah = ['dstrike', 'outline', 'shadow', 'emboss', 'imprint', 'noProof', 'snapToGrid', 'vanish', 'webHidden', 'color', 'spacing', 'w', 'kern', 'position', 'sz', 'szCs', 'highlight', 'u', 'effect', 'bdr', 'shd', 'fitText', 'vertAlign', 'rtl', 'cs', 'em', 'lang', 'eastAsianLayout', 'specVanish', 'oMath'];
    foreach ($rpr->childNodes as $c) {
        if ($c instanceof DOMElement && in_array($c->localName, $setelah, true)) {
            $rpr->insertBefore($s, $c);

            return;
        }
    }
    $rpr->appendChild($s);
};

/**
 * Tandai rentang teks (frasa) pada paragraf dengan penanda coret.
 * Run yang terpotong di tengah dipecah dulu supaya hanya frasa itu yang tercoret.
 */
$coretFrasa = function (DOMElement $p, string $frasa, string $penanda) use ($runs, $xp, $teks, $pasangCoret) {
    $full = $teks($p);
    $mulai = mb_stripos($full, $frasa);
    $mulai !== false || exit("Frasa \"$frasa\" tidak ditemukan\n");
    $akhir = $mulai + mb_strlen($frasa);
    foreach ([$mulai, $akhir] as $potong) {
        $pos = 0;
        foreach ($runs($p) as $r) {
            $t = $xp->query('./w:t', $r)->item(0);
            $len = $t ? mb_strlen($t->textContent) : 0;
            if ($t && $potong > $pos && $potong < $pos + $len) {
                $kanan = $r->cloneNode(true);
                $xp->query('./w:t', $kanan)->item(0)->textContent = mb_substr($t->textContent, $potong - $pos);
                $t->textContent = mb_substr($t->textContent, 0, $potong - $pos);
                foreach ([$t, $xp->query('./w:t', $kanan)->item(0)] as $tt) {
                    $tt->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
                }
                $r->parentNode->insertBefore($kanan, $r->nextSibling);
                break;
            }
            $pos += $len;
        }
    }
    $pos = 0;
    foreach ($runs($p) as $r) {
        $t = $xp->query('./w:t', $r)->item(0);
        $len = $t ? mb_strlen($t->textContent) : 0;
        if ($t && $pos >= $mulai && $pos + $len <= $akhir && $len > 0) {
            $pasangCoret($r, $penanda);
        }
        $pos += $len;
    }
};

// ---------- Kepala: nomor & data sampel ----------
$isiSetelahTitikDua($paragraf('/^\s*Nomor\s*\/\s*Number/i'), '{{nomor}}');
foreach ([
    '/Pelanggan\s*\/\s*Customer/i' => '{{pelanggan}}',
    '/Alamat\s*\/\s*Address/i' => '{{alamat}}',
    '/Tanggal Penerimaan/i' => '{{tgl_terima}}',
    '/Jenis sampel/i' => '{{jenis_sampel}}',
    '/Kode sampel/i' => '{{kode_sampel}}',
    '/Tanggal Pengujian/i' => '{{tgl_uji}}',
] as $re => $penanda) {
    $isiSetelahTitikDua($paragraf($re), $penanda);
}

// ---------- Tabel hasil: dua baris contoh (awal bidang & lanjutan bidang) ----------
$tbl = $xp->query('//w:body/w:tbl')->item(0) ?? exit("Tabel hasil tidak ditemukan\n");
$baris = iterator_to_array($xp->query('./w:tr', $tbl));
count($baris) >= 3 || exit("Tabel hasil minimal punya 2 baris isi\n");
foreach (array_slice($baris, 3) as $tr) {
    $tbl->removeChild($tr);
}
$selMetode = null;
foreach ([1 => ['{{no}}', '{{bidang}}'], 2 => [null, null]] as $i => [$no, $bidang]) {
    $sel = iterator_to_array($xp->query('./w:tc', $baris[$i]));
    count($sel) === 7 || exit("Baris tabel harus 7 kolom\n");
    $penanda = [$no, $bidang, '{{param}}', '{{hasil}}', '{{syarat}}', '{{metode}}', '{{ket}}'];
    $contohMetode = $xp->query('.//w:r', $sel[5])->item(0);
    foreach ($sel as $k => $tc) {
        if ($penanda[$k] === null) {
            continue; // sel gabungan (vMerge) baris lanjutan: dibiarkan kosong
        }
        $ps = iterator_to_array($xp->query('./w:p', $tc));
        if ($k === 1) {
            // Bidang: "Biologi/" + baris kedua miring "Biology"
            $isiParagraf($ps[0], '{{bidang}}');
            isset($ps[1]) ? $isiParagraf($ps[1], '{{bidang_en}}') : null;
            foreach (array_slice($ps, 2) as $p) {
                $tc->removeChild($p);
            }
            continue;
        }
        foreach (array_slice($ps, 1) as $p) {
            $tc->removeChild($p);
        }
        // Keterangan tidak punya run contoh: pakai format kolom metode.
        $r = $isiParagraf($ps[0], $penanda[$k], $xp->query('.//w:r', $ps[0])->item(0) ?? $contohMetode);
        if ($k === 2) {
            foreach ($xp->query('./w:rPr/w:i | ./w:rPr/w:iCs', $r) as $it) {
                $it->parentNode->removeChild($it); // miring diatur per parameter (_nama spesies_)
            }
        }
    }
}

// ---------- Catatan 1: pilihan coret ----------
$pId = $paragraf('/mewakili populasi/i');
$coretFrasa($pId, 'Hasil uji ini mewakili populasi yang diambil', '{{S_POP}}');
$coretFrasa($pId, 'Hasil Uji ini hanya berlaku untuk contoh uji yang diuji', '{{S_CONTOH}}');
$pEn = $paragraf('/represent the population/i');
$coretFrasa($pEn, 'This result of the test represent the population taken', '{{S_POP}}');
$coretFrasa($pEn, 'This result of the test are only valid for the tested sample', '{{S_CONTOH}}');

// ---------- Tanda tangan ----------
$isiParagraf($paragraf('/^\s*\S+,\s*\d{1,2}\s+\S+\s+\d{4}\s*$/u'), '{{kota_tanggal}}');
$pJab = $paragraf('/Kepala UPT\s*\/\s*Penanggung jawab/i');
$coretFrasa($pJab, 'Kepala UPT', '{{S_KEPALA}}');
$coretFrasa($pJab, 'Penanggung jawab Laboratorium', '{{S_PJ}}');
$pJabEn = $paragraf('/Head of TIU\s*\/\s*Laboratory/i');
$coretFrasa($pJabEn, 'Head of TIU', '{{S_KEPALA}}');
$coretFrasa($pJabEn, 'Laboratory in Charge', '{{S_PJ}}');
$pNip = $paragraf('/^\s*NIP\.?\s*\d/');
$isiParagraf($pNip, 'NIP. {{ttd_nip}}');
// Nama penandatangan = paragraf berisi teks tepat sebelum baris NIP.
$prev = $pNip->previousSibling;
while ($prev && ! ($prev instanceof DOMElement && $prev->localName === 'p' && trim($teks($prev)) !== '')) {
    $prev = $prev->previousSibling;
}
$prev || exit("Nama penandatangan tidak ditemukan\n");
$isiParagraf($prev, '{{ttd_nama}}');

// ---------- Margin halaman ----------
// Mayoritas LHP asli (semua layanan) bermargin bawah 72 pt = 1440 twip; margin atas (ruang kop) dibiarkan dari file sumber.
foreach ($xp->query('//w:body/w:sectPr/w:pgMar') as $mar) {
    $mar->setAttributeNS(W, 'w:bottom', '1440');
}

$zip->addFromString('word/document.xml', $dom->saveXML());
$zip->close();
echo "Template dibuat: ".realpath($dst)."\n";
