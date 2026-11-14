<?php

namespace App\Enums;

/**
 * Jenis pengguna jasa, dipilih pengguna setiap kali mengajukan permohonan.
 * Menentukan kapan membayar:
 * - MANDIRI  : bayar di awal (setelah sampel diverifikasi), baru diuji.
 * - SURVEILEN: sementara sama dengan Mandiri (bayar di awal).
 * - EKSTERNAL: sampel langsung diuji; bayar setelah LHU jadi. LHU baru bisa diunduh setelah lunas.
 */
enum JenisBayar: string
{
    case Mandiri = 'MANDIRI';
    case Eksternal = 'EKSTERNAL';
    case Surveilen = 'SURVEILEN';

    public function label(): string
    {
        return match ($this) {
            self::Mandiri => 'Mandiri',
            self::Eksternal => 'Eksternal',
            self::Surveilen => 'Surveilen',
        };
    }

    public function keterangan(): string
    {
        return $this->bayarDiAwal()
            ? 'Bayar di awal, sebelum sampel diuji'
            : 'Bayar setelah LHU jadi; LHU diambil setelah lunas';
    }

    /** true = tagihan dibayar sebelum sampel diuji (Mandiri, Surveilen). */
    public function bayarDiAwal(): bool
    {
        return $this !== self::Eksternal;
    }
}
