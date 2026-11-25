<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/** Membuat dokumen PDF dari view Blade (LHP, bukti terima). Tanpa akses jaringan/berkas luar. */
class Pdf
{
    public static function dari(string $view, array $data, string $orientasi = 'portrait'): string
    {
        $opsi = new Options;
        $opsi->set('defaultFont', 'DejaVu Sans'); // mendukung µ, β, ³, ⁵
        $opsi->set('isRemoteEnabled', false);
        $opsi->set('chroot', public_path());
        $opsi->set('tempDir', sys_get_temp_dir());
        $opsi->set('fontCache', sys_get_temp_dir());

        $pdf = new Dompdf($opsi);
        $pdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $pdf->setPaper('A4', $orientasi);
        $pdf->render();

        return (string) $pdf->output();
    }

    public static function respons(string $view, array $data, string $namaBerkas, bool $unduh = false, string $orientasi = 'portrait'): Response
    {
        return response(self::dari($view, $data, $orientasi), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($unduh ? 'attachment' : 'inline', $namaBerkas, preg_replace('/[^A-Za-z0-9._-]/', '_', $namaBerkas)),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Logo resmi sebagai data URI: 'kkp' (Kementerian Kelautan dan Perikanan) atau 'kan' (Komite Akreditasi Nasional). */
    public static function logo(string $nama): string
    {
        $isi = @file_get_contents(public_path("img/logo-{$nama}.png"));

        return $isi ? 'data:image/png;base64,'.base64_encode($isi) : '';
    }

    /** Gambar SVG sebagai data URI untuk <img> di PDF. */
    public static function svg(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
