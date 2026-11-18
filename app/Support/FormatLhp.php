<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Aturan penulisan isian LHP, dipakai bersama oleh file Word (LhpWord) dan tampilan web,
 * supaya yang dilihat Administrasi sama persis dengan yang tercetak.
 * Penanda di teks: _miring_ (nama spesies), 10^5 (pangkat), baris baru "\n".
 */
class FormatLhp
{
    private const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    /** "02 Juni 2026" seperti di LHP. */
    public static function tanggal(CarbonInterface|string|null $dt): string
    {
        if (! $dt) {
            return '';
        }
        $c = Carbon::parse($dt)->setTimezone(config('app.timezone'));

        return sprintf('%02d %s %d', $c->day, self::BULAN[$c->month - 1], $c->year);
    }

    public static function romawi(int $n): string
    {
        $out = '';
        foreach (['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1] as $r => $v) {
            while ($n >= $v) {
                $out .= $r;
                $n -= $v;
            }
        }

        return $out;
    }

    /**
     * Satu nilai hasil seperti ditulis di kolom "Hasil (satuan)".
     * Satuan ditambahkan hanya untuk hasil berupa angka yang belum menyebut satuannya
     * ("2,3 x 10^4" -> "2,3 x 10^4 kol/g"; "Negatif/25 g", "ND", "8,0" tanpa satuan tetap apa adanya).
     */
    public static function nilai(string $v, ?string $satuan): string
    {
        $v = trim(preg_replace('/\s+/u', ' ', $v));
        $satuan = trim((string) $satuan);
        if ($v === '' || $satuan === '') {
            return $v;
        }
        if (preg_match('/^[<>≤≥]?\s*\d/u', $v) && ! str_ends_with(mb_strtolower($v), mb_strtolower($satuan))) {
            return $v.' '.$satuan;
        }

        return $v;
    }

    /**
     * Isi sel hasil, satu ulangan per baris. Baris kosong dipertahankan (sensory test: baris judul
     * kelompok tanpa nilai) supaya nilai sejajar dengan sub-item di kolom Parameter.
     *
     * @param  array<int, string>  $ulangan
     */
    public static function hasil(array $ulangan, ?string $satuan): string
    {
        return rtrim(implode("\n", array_map(fn ($v) => self::nilai((string) $v, $satuan), array_values($ulangan))), "\n");
    }

    /**
     * Pecah teks bertanda menjadi potongan berformat.
     *
     * @return array<int, array<int, array{0:string, 1:bool, 2:bool}>> per baris: [teks, miring, pangkat]
     */
    public static function potong(string $teks): array
    {
        $baris = [];
        foreach (preg_split('/\R/u', $teks) as $line) {
            $seg = [];
            foreach (preg_split('/(_[^_]+_)/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $bagian) {
                $miring = mb_strlen($bagian) > 2 && str_starts_with($bagian, '_') && str_ends_with($bagian, '_');
                $isi = $miring ? mb_substr($bagian, 1, -1) : $bagian;
                foreach (preg_split('/\^(-?\d+)/u', $isi, -1, PREG_SPLIT_DELIM_CAPTURE) as $i => $p) {
                    if ($p !== '') {
                        $seg[] = [$p, $miring, $i % 2 === 1];
                    }
                }
            }
            $baris[] = $seg;
        }

        return $baris;
    }

    /** Versi HTML (sudah di-escape) untuk pratinjau web: <i>, <sup>, <br>. */
    public static function html(?string $teks): string
    {
        if ($teks === null || $teks === '') {
            return '';
        }
        $out = [];
        foreach (self::potong($teks) as $seg) {
            $out[] = implode('', array_map(function ($s) {
                // Spasi awal (indentasi sub-item) tetap terlihat di HTML.
                $h = preg_replace_callback('/^ +/', fn ($m) => str_repeat('&nbsp;', strlen($m[0])), e($s[0]));
                $h = $s[2] ? "<sup>{$h}</sup>" : $h;

                return $s[1] ? "<i>{$h}</i>" : $h;
            }, $seg));
        }

        return implode('<br>', $out);
    }

    /** Teks satu baris untuk elemen form (mis. <option>): pangkat jadi karakter superscript, tanpa penanda miring. */
    public static function sebaris(?string $teks): string
    {
        $angka = ['0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴', '5' => '⁵', '6' => '⁶', '7' => '⁷', '8' => '⁸', '9' => '⁹', '-' => '⁻'];

        return preg_replace_callback('/\^(-?\d+)/u', fn ($m) => strtr($m[1], $angka), self::polos($teks));
    }

    /** Teks polos tanpa penanda (untuk pencarian, audit, sidik). */
    public static function polos(?string $teks): string
    {
        return str_replace('_', '', (string) $teks);
    }
}
