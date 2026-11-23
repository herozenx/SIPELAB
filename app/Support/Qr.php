<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** QR Code SVG (tanpa ekstensi gambar PHP), dipakai di LHP untuk cek keaslian. */
class Qr
{
    public static function svg(string $isi, int $ukuran = 112): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($ukuran, 1), new SvgImageBackEnd)))->writeString($isi);

        // Buang deklarasi XML agar bisa disisipkan langsung di HTML.
        return trim((string) preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }
}
