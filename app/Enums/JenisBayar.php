<?php

namespace App\Enums;

/**
 * Jenis pengguna jasa menentukan kapan membayar:
 * - MANDIRI  : bayar di awal (setelah sampel diverifikasi), baru diuji.
 * - EKSTERNAL: sampel langsung diuji; bayar setelah LHU jadi. LHU baru bisa diunduh setelah lunas.
 */
enum JenisBayar: string
{
    case Mandiri = 'MANDIRI';
    case Eksternal = 'EKSTERNAL';

    public function label(): string
    {
        return $this === self::Mandiri ? 'Mandiri' : 'Eksternal';
    }

    public function keterangan(): string
    {
        return $this === self::Mandiri
            ? 'Bayar di awal, sebelum sampel diuji'
            : 'Bayar setelah LHU jadi; LHU diambil setelah lunas';
    }
}
