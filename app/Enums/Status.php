<?php

namespace App\Enums;

enum Status: string
{
    case Diajukan = 'DIAJUKAN';
    case Ditolak = 'DITOLAK';
    case MenungguPembayaran = 'MENUNGGU_PEMBAYARAN';
    case MenungguKonfirmasiBayar = 'MENUNGGU_KONFIRMASI_BAYAR';
    case DiLab = 'DI_LAB';
    case MenungguValidasi = 'MENUNGGU_VALIDASI';
    case Selesai = 'SELESAI';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Menunggu verifikasi',
            self::Ditolak => 'Ditolak',
            self::MenungguPembayaran => 'Menunggu pembayaran',
            self::MenungguKonfirmasiBayar => 'Cek pembayaran',
            self::DiLab => 'Sedang diuji',
            self::MenungguValidasi => 'Menunggu validasi LHU',
            self::Selesai => 'LHU terbit',
        };
    }

    /** Nada warna badge: info | wait | lab | ok | bad */
    public function tone(): string
    {
        return match ($this) {
            self::Diajukan, self::MenungguKonfirmasiBayar, self::MenungguValidasi => 'wait',
            self::MenungguPembayaran => 'info',
            self::DiLab => 'lab',
            self::Selesai => 'ok',
            self::Ditolak => 'bad',
        };
    }
}
