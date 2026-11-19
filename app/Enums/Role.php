<?php

namespace App\Enums;

enum Role: string
{
    case Superadmin = 'superadmin';
    case AdminLab = 'admin_lab';
    case Administrasi = 'administrasi';
    case PetugasLab = 'petugas_lab';
    case PenggunaJasa = 'pengguna_jasa';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::AdminLab => 'Admin Lab',
            self::Administrasi => 'Administrasi',
            self::PetugasLab => 'Petugas Lab',
            self::PenggunaJasa => 'Pengguna Jasa',
        };
    }

    /** Admin Lab & Superadmin: verifikasi, pembayaran, penerbitan LHP. Administrasi bukan staff (hanya memeriksa). */
    public function isStaff(): bool
    {
        return $this === self::AdminLab || $this === self::Superadmin;
    }
}
