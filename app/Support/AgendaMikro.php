<?php

namespace App\Support;

/**
 * Lembar "Agenda Pemeriksaan" mikrobiologi (L/OK/SUB II/8.6c), disalin dari formulir kiriman Balai
 * (grup LHP Surabaya II, 26 Nov 2026). Satu definisi per metode; dipilih berdasarkan metode parameter.
 *
 * Tiap definisi:
 * - judul/metode : dua baris judul formulir
 * - awal         : judul kolom Tgl & kode sampel (penulisan mengikuti formulir)
 * - baris        : label sub-baris per sampel (satu array label per sub-baris), [[]] = satu baris
 * - kolom        : daftar kolom setelah kode. Jenis:
 *     ['lbl' => i, 'h' => judul, 'g' => grup, 'gabung' => bool]  kolom label baris ke-i (tidak diisi)
 *     ['k' => kunci, 'h' => judul, 'g' => grup, 'per' => 'baris'|'sampel'|'lbl0']  sel isian
 *       per=sampel : satu sel per sampel; per=lbl0 : satu sel per kelompok label pertama
 *     kunci 'hasil' (per sampel) = hasil sampel yang masuk ke LHP
 * - akhir        : judul kolom tanggal keluar/hasil (Analis & Penyelia selalu menyusul)
 * - acuan        : baris reaksi acuan di bawah judul (label + nilai per kunci), opsional
 * - ket          : catatan kaki formulir
 */
final class AgendaMikro
{
    public const KODE_FORMULIR = 'L/OK/SUB II/8.6c';

    public static function semua(): array
    {
        static $def = null;

        return $def ??= self::definisi();
    }

    /** Definisi lembar untuk metode parameter, atau null bila bukan parameter berformulir agenda. */
    public static function untukMetode(?string $metode): ?array
    {
        $m = preg_replace('/\s+/', '', (string) $metode);
        foreach (self::semua() as $jenis => $d) {
            foreach ($d['cocok'] as $pola) {
                if ($pola !== '' && str_contains($m, $pola)) {
                    return $d + ['jenis' => $jenis];
                }
            }
        }

        return null;
    }

    public static function jenis(string $jenis): ?array
    {
        $d = self::semua()[$jenis] ?? null;

        return $d ? $d + ['jenis' => $jenis] : null;
    }

    /** Kolom isian saja (tanpa kolom label). */
    public static function kolomIsian(array $d): array
    {
        return array_values(array_filter($d['kolom'], fn ($c) => isset($c['k'])));
    }

    /**
     * Daftar kunci sel yang sah untuk satu sampel, dipakai saat menyimpan:
     * per baris "r{i}.{k}", per kelompok label pertama "g{i}.{k}" (i = baris pertama kelompok), per sampel "{k}".
     */
    public static function kunciSah(array $d): array
    {
        $kunci = [];
        $awalKelompok = self::awalKelompok($d);
        foreach (self::kolomIsian($d) as $c) {
            $per = $c['per'] ?? 'baris';
            if ($per === 'sampel') {
                $kunci[] = $c['k'];
            } elseif ($per === 'lbl0') {
                foreach ($awalKelompok as $i => $n) {
                    $kunci[] = "g{$i}.{$c['k']}";
                }
            } else {
                foreach (array_keys($d['baris']) as $i) {
                    $kunci[] = "r{$i}.{$c['k']}";
                }
            }
        }

        return $kunci;
    }

    /** Baris pertama tiap kelompok label pertama => jumlah baris kelompok itu. */
    public static function awalKelompok(array $d): array
    {
        $hasil = [];
        $mulai = null;
        foreach ($d['baris'] as $i => $lbl) {
            if ($mulai === null || ($d['baris'][$mulai][0] ?? null) !== ($lbl[0] ?? null)) {
                $mulai = $i;
                $hasil[$i] = 0;
            }
            $hasil[$mulai]++;
        }

        return $hasil;
    }

    /** Rentang (rowspan) sel label baris i pada kolom label ke-l bila digabung; 0 = tertutup sel di atasnya. */
    public static function rentangLabel(array $d, int $l, int $i): int
    {
        $b = $d['baris'];
        if ($i > 0 && ($b[$i - 1][$l] ?? null) === ($b[$i][$l] ?? null) && ($l === 0 || ($b[$i - 1][0] ?? null) === ($b[$i][0] ?? null))) {
            return 0;
        }
        $n = 1;
        while (isset($b[$i + $n]) && ($b[$i + $n][$l] ?? null) === ($b[$i][$l] ?? null) && ($l === 0 || ($b[$i + $n][0] ?? null) === ($b[$i][0] ?? null))) {
            $n++;
        }

        return $n;
    }

    /**
     * Blok sampel satu parameter untuk ditampilkan di lembar: satu blok per sampel (jumlah pengujian).
     * Butuh relasi permohonan.tahap, permohonan.persetujuan.user, produk, analis, penginput.
     * Bila hanya satu sampel, kolom Hasil lembar = hasil LHP parameter (tidak disimpan dua kali).
     */
    public static function blok(\App\Models\PermohonanParameter $x, ?\App\Models\Permohonan $p = null): array
    {
        $p ??= $x->permohonan;
        $tahap = $p->tahap->firstWhere('lab_id', $x->lab_id);
        $paraf = $p->persetujuan->first(fn ($s) => $s->jenis === \App\Models\Persetujuan::PARAF && $s->lab_id === $x->lab_id);
        $kode = \App\Models\Pengembalian::kodeUjiUlang($x->produk?->kode ?: $p->kodeTampil(), $p->pengembalian, $x->lab_id);
        $n = max(1, (int) $x->jumlah);
        $isi = is_array($x->lembar_kerja) ? $x->lembar_kerja : [];
        $blok = [];
        for ($s = 1; $s <= $n; $s++) {
            $sel = is_array($isi["s{$s}"] ?? null) ? $isi["s{$s}"] : [];
            if ($n === 1) {
                $sel['hasil'] = $x->hasil;
            }
            $blok[] = [
                'no' => $s,
                'tgl' => $tahap?->masuk_at ?? $p->masuk_lab_at,
                'kode' => $kode,
                'isi' => $sel,
                'keluar' => $tahap?->selesai_at,
                'analis' => $x->analis?->nama ?? $x->penginput?->nama,
                'penyelia' => $paraf?->user?->nama,
            ];
        }

        return $blok;
    }

    /** Dua baris judul tabel: [ [ [teks, colspan, rowspan], ... ], [ [teks], ... ] ]. */
    public static function judulKolom(array $d): array
    {
        $atas = [];
        $bawah = [];
        $kolom = $d['kolom'];
        for ($i = 0, $n = count($kolom); $i < $n; $i++) {
            $g = $kolom[$i]['g'] ?? null;
            if ($g === null) {
                $atas[] = [$kolom[$i]['h'] ?? '', 1, 2];

                continue;
            }
            $j = $i;
            $anak = [];
            while ($j < $n && ($kolom[$j]['g'] ?? null) === $g) {
                $anak[] = $kolom[$j]['h'] ?? '';
                $j++;
            }
            if (implode('', $anak) === '') {
                $atas[] = [$g, count($anak), 2];
            } else {
                $atas[] = [$g, count($anak), 1];
                foreach ($anak as $a) {
                    $bawah[] = [$a];
                }
            }
            $i = $j - 1;
        }

        return [$atas, $bawah];
    }

    private static function definisi(): array
    {
        $pengenceran = ['10⁻¹', '10⁻²', '10⁻³'];
        $tabung = [];
        foreach ($pengenceran as $p) {
            foreach ([1, 2, 3] as $t) {
                $tabung[] = ["{$p}.{$t}"];
            }
        }
        $perPengenceran = function (string $kunci, string $grup) use ($pengenceran) {
            $out = [];
            foreach ($pengenceran as $i => $p) {
                $out[] = ['k' => $kunci.($i + 1), 'h' => $p, 'g' => $grup];
            }

            return $out;
        };
        $sederhana = fn (array $daftar, ?string $grup = null) => array_map(
            fn ($x) => ['k' => $x[0], 'h' => $x[1], 'g' => $grup], $daftar);

        return [
            'CM' => [
                'per_halaman' => 35,
                'cocok' => ['9308-1'],
                'judul' => 'AGENDA PEMERIKSAAN <i>E. coli dan Coliform</i>',
                'metode' => 'METODE ISO 9308-1:2014',
                'awal' => ['Tgl', 'KODE'],
                'baris' => [[]],
                'tegak' => true,
                'kolom' => [
                    ['k' => 'cca_merah', 'h' => 'merah muda-merah', 'g' => 'CCA'],
                    ['k' => 'cca_biru', 'h' => 'biru tua-ungu', 'g' => 'CCA'],
                    ['k' => 'oksidase', 'h' => 'Oksidase'],
                    ['k' => 'hasil', 'h' => 'Hasil<br>(koloni/100mL)', 'per' => 'sampel'],
                ],
                'akhir' => 'Tgl<br>keluar',
            ],
            'E1' => [
                'per_halaman' => 28,
                'cocok' => ['16649-3'],
                'judul' => 'AGENDA PEMERIKSAAN <i>E. coli</i>',
                'metode' => 'METODE SNI ISO 16649-3.1<sup>st</sup>.2015',
                'awal' => ['Tgl', 'KODE'],
                'baris' => [[]],
                'tegak' => true,
                'kolom' => [
                    ['k' => 'mmgm_warna', 'h' => 'Warna', 'g' => 'MMGM'],
                    ['k' => 'mmgm_gas', 'h' => 'Gas', 'g' => 'MMGM'],
                    ['k' => 'tbx', 'h' => 'TBX Agar'],
                    ['k' => 'hasil', 'h' => 'Hasil', 'per' => 'sampel'],
                ],
                'akhir' => 'Tgl<br>Keluar',
            ],
            'E2' => [
                'per_halaman' => 3,
                'cocok' => ['2332.1'],
                'judul' => 'AGENDA PEMERIKSAAN <i>E. coli</i> / Coliform',
                'metode' => 'METODE SNI 2332.1-2015',
                'awal' => ['Tgl', 'KODE'],
                'baris' => $tabung,
                'kolom' => array_merge(
                    [['lbl' => 0, 'h' => '']],
                    $perPengenceran('lsb', 'LSB'), $perPengenceran('bglb', 'BGLB'), $perPengenceran('ec', 'EC Broth'),
                    $perPengenceran('emba', 'EMBA'), $perPengenceran('pca', 'PCA Miring'), $perPengenceran('indole', 'Indole'),
                    $perPengenceran('mr', 'MR'), $perPengenceran('vp', 'VP'), $perPengenceran('citrate', 'Citrate'),
                    [['k' => 'hasil', 'h' => 'Hasil APM/g', 'per' => 'sampel']],
                ),
                'akhir' => 'Tgl<br>Keluar',
            ],
            'SA' => [
                'per_halaman' => 10,
                'cocok' => ['2332.9'],
                'judul' => 'Agenda Pemeriksaan <i>Staphylococcus aureus</i>',
                'metode' => 'Metode SNI 2332.9 - 2015',
                'awal' => ['Tgl', 'No Sampel'],
                'baris' => [['0,3 mL'], ['0,3 mL'], ['0,4 mL']],
                'kolom' => array_merge(
                    [['lbl' => 0, 'h' => 'Plate']],
                    $perPengenceran('bpa', 'BPA'), $perPengenceran('bhib', 'BHIB'), $perPengenceran('koagulasi', 'Uji Koagulasi'),
                    $perPengenceran('katalase', 'Uji Katalase'), $perPengenceran('glukosa', 'Uji Glukosa Anaerob'),
                    $perPengenceran('manitol', 'Uji Manitol Anaerob'), $perPengenceran('nuklease', 'Uji Nuklease'),
                    [['k' => 'hasil', 'h' => 'Hasil<br>(kol/gr)', 'per' => 'sampel']],
                ),
                'akhir' => 'Tgl<br>Hasil',
                'ket' => 'TB = Terlalu banyak untuk dihitung',
            ],
            'S' => [
                'per_halaman' => 7,
                'cocok' => ['6579'],
                'judul' => 'AGENDA PEMERIKSAAN <i>Salmonella</i> sp.',
                'metode' => 'METODE SNI ISO 6579.2017',
                'awal' => ['TGL', 'KODE'],
                'baris' => [['RV', 'XLD'], ['RV', 'BSA/HE'], ['MKTTN', 'XLD'], ['MKTTN', 'BSA/HE']],
                'kolom' => array_merge(
                    [
                        ['k' => 'bpw', 'h' => 'BPW', 'per' => 'sampel'],
                        ['lbl' => 0, 'h' => '', 'g' => '<i>Selective enrichment</i>', 'gabung' => true],
                        ['k' => 'enrichment', 'h' => '', 'g' => '<i>Selective enrichment</i>', 'per' => 'lbl0'],
                        ['lbl' => 1, 'h' => '', 'g' => '<i>Plating out *</i>'],
                        ['k' => 'plating', 'h' => '', 'g' => '<i>Plating out *</i>'],
                    ],
                    $sederhana([['tsia', 'TSIA'], ['tsia_h2s', 'H2S'], ['lia', 'LIA'], ['lia_h2s', 'H2S'], ['urea', 'Urea'],
                        ['onpg', 'ONPG'], ['tb', 'TB'], ['o', 'O-'], ['h', 'H-'], ['vi', 'Vi-']]),
                    [['k' => 'hasil', 'h' => 'Hasil', 'per' => 'sampel']],
                ),
                'akhir' => 'Tgl Hasil',
                'acuan' => ['label' => '<i>Salmonella</i> (SNI)'],
                'ket' => '*Ket : Pink (P), Pink Hitam (PH), Kuning (K), Kuning Hitam (KH), No Growth (NG)',
            ],
            'VC' => [
                'per_halaman' => 11,
                'cocok' => ['2332.4'],
                'judul' => 'AGENDA PEMERIKSAAN <i>Vibrio Cholerae</i>',
                'metode' => 'SNI 01 2332.4-2006',
                'awal' => ['Tgl', 'No sampel'],
                'baris' => [['10⁻¹'], ['10⁻²'], ['10⁻³']],
                'kolom' => array_merge(
                    [
                        ['lbl' => 0, 'h' => 'APW (36±1ºC, 16-18j)', 'g' => 'PENGKAYAAN'],
                        ['k' => 'apw_6', 'h' => '6-8j', 'g' => 'PENGKAYAAN'],
                        ['k' => 'apw_16', 'h' => '16-24j', 'g' => 'PENGKAYAAN'],
                        ['k' => 'tcbs', 'h' => 'TCBS', 'g' => 'SELEKTIF'],
                        ['k' => 'tsa', 'h' => 'TSA 1,5%', 'g' => 'PEMURNIAN'],
                    ],
                    $sederhana([['oks', 'Oks'], ['gram', 'Gram'], ['of', 'OF'], ['tsia', 'TSIA'], ['o129_10', '0/129<br>10 µg'], ['o129_150', '0/129<br>150 µg']], 'BIOKIMIA PENDAHULUAN'),
                    $sederhana([['inaba', 'inaba'], ['ogawa', 'ogawa']], 'SEROLOGI'),
                    $sederhana([['urea', 'Urea'], ['t1n0', 'T1N0'], ['t1n3', 'T1N3'], ['t1n6', 'T1N6'], ['s42', '42 ºC'], ['arg', 'Arg'],
                        ['lia', 'LIA'], ['mio', 'MIO'], ['suk', 'Suk'], ['lak', 'Lak'], ['man', 'Man'], ['vp', 'VP']], 'BIOKIMIA LANJUTAN'),
                    [
                        ['k' => 'hasil_baris', 'h' => 'Hasil'],
                        ['k' => 'hasil', 'h' => 'Hasil<br>/ 25g', 'per' => 'sampel'],
                    ],
                ),
                'akhir' => 'Tgl Hasil',
                'acuan' => ['label' => '<i>V. cholerae</i> (SNI)', 'apw_6' => '+', 'apw_16' => '+', 'tcbs' => 'K', 'tsa' => '+', 'oks' => '+',
                    'gram' => '-', 'of' => 'O/F', 'tsia' => 'A/A', 'o129_10' => 'S', 'o129_150' => 'S', 'inaba' => '+', 'ogawa' => '+',
                    'urea' => '-', 't1n0' => '+', 't1n3' => '+', 't1n6' => '-', 's42' => '+', 'arg' => '-', 'lia' => '+', 'mio' => '+',
                    'suk' => '+', 'lak' => '-', 'man' => '+', 'vp' => 'V'],
                'ket' => '*Ket : Kuning (<b>K</b>), Hijau (<b>H</b>), No Growth (<b>NG</b>)',
            ],
            'VP' => [
                'per_halaman' => 4,
                'cocok' => ['2332.5'],
                'judul' => 'AGENDA PEMERIKSAAN <i>Vibrio Parahaemolyticus</i>',
                'metode' => 'SNI 01 2332.5-2006',
                'awal' => ['Tanggal', 'No Sampel'],
                'baris' => $tabung,
                'kolom' => array_merge(
                    [
                        ['lbl' => 0, 'h' => 'APW (35-37ºC, 16-18j)', 'g' => 'PENGKAYAAN'],
                        ['k' => 'apw', 'h' => 'Hasil', 'g' => 'PENGKAYAAN'],
                        ['k' => 'tcbs', 'h' => 'TCBS', 'g' => 'AGAR SELEKTIF'],
                        ['k' => 'tsa', 'h' => 'TSA 1,5%', 'g' => 'PEMURNIAN'],
                    ],
                    $sederhana([['oks', 'Oks'], ['gram', 'Gram'], ['motil', 'Motil'], ['tsia', 'TSIA'], ['of', 'OF'], ['onpg', 'ONPG'],
                        ['o129_10', '0/129<br>10 µg'], ['o129_150', '0/129<br>150 µg']], 'BIOKIMIA PENDAHULUAN'),
                    $sederhana([['urea', 'Urea'], ['t1n0', 'T1N0'], ['t1n3', 'T1N3'], ['t1n6', 'T1N6'], ['t1n8', 'T1N8'], ['t1n10', 'T1N10'],
                        ['s42', '42ºC'], ['arg', 'Arg'], ['lia', 'LIA'], ['mio', 'MIO'], ['suk', 'Suk'], ['lak', 'Lak'], ['ara', 'Ara'],
                        ['man', 'Man'], ['vp', 'VP']], 'BIOKIMIA LANJUTAN'),
                    [
                        ['k' => 'hasil_baris', 'h' => 'Hasil'],
                        ['k' => 'hasil', 'h' => 'APM/g', 'per' => 'sampel'],
                    ],
                ),
                'akhir' => 'Tgl Hasil',
                'acuan' => ['label' => '<i>V. parahaemolyticus</i> (SNI)', 'apw' => '+', 'tcbs' => 'H', 'tsa' => '+', 'oks' => '+', 'gram' => '-',
                    'motil' => '+', 'tsia' => 'K/A', 'of' => 'O/F', 'onpg' => '-', 'o129_10' => 'R', 'o129_150' => 'S', 'urea' => '+',
                    't1n0' => '-', 't1n3' => '+', 't1n6' => '+', 't1n8' => '+', 't1n10' => '-', 's42' => '+', 'arg' => '-', 'lia' => '+',
                    'mio' => '+', 'suk' => '-', 'lak' => '-', 'ara' => '+', 'man' => '+', 'vp' => '-'],
                'ket' => 'Ket : Hijau/hijau kebiruan 2-3 mm (<b>H</b>), Hijau &lt;2mm / &gt;3mm (<b>HK</b>), Kuning (<b>K</b>)',
            ],
        ];
    }
}
