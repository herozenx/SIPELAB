<?php

namespace App\Support;

/**
 * Pangkat pada hasil uji & persyaratan mutu (mis. 5,0 x 10⁵ kol/g).
 * Analis mengetik pangkat lewat tombol x² (karakter ⁰–⁹) atau dengan ^ (10^5, 10^{-2});
 * keduanya dicetak sebagai <sup> agar tampil benar di LHP/LHU, termasuk PDF yang fontnya tidak punya glyph ⁰–⁹.
 */
class Pangkat
{
    private const ATAS = ['⁰' => '0', '¹' => '1', '²' => '2', '³' => '3', '⁴' => '4', '⁵' => '5', '⁶' => '6', '⁷' => '7', '⁸' => '8', '⁹' => '9', '⁻' => '-', '⁺' => '+'];

    /** Teks aman-HTML dengan pangkat sebagai <sup>. */
    public static function html(?string $teks): string
    {
        if ($teks === null || $teks === '') {
            return '';
        }
        $s = e($teks);
        $s = preg_replace_callback('/[⁰¹²³⁴⁵⁶⁷⁸⁹⁻⁺]+/u', fn ($m) => '<sup>'.strtr($m[0], self::ATAS).'</sup>', $s);
        $s = preg_replace('/\^\{([^}]{1,10})\}/u', '<sup>$1</sup>', $s);

        return preg_replace('/\^([+\-−]?[0-9]{1,4})/u', '<sup>$1</sup>', $s);
    }
}
