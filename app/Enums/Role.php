<?php

namespace App\Enums;

enum Role: string
{
    case Superadmin = 'superadmin';
    case AdminLab = 'admin_lab';
    case PetugasLab = 'petugas_lab';
    case PenggunaJasa = 'pengguna_jasa';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::AdminLab => 'Admin Lab',
            self::PetugasLab => 'Petugas Lab',
            self::PenggunaJasa => 'Pengguna Jasa',
        };
    }

    /** Pilihan "Masuk sebagai" pada form login (sesuai SKPL). */
    public function portal(): string
    {
        return match ($this) {
            self::Superadmin => 'superadmin',
            self::AdminLab, self::PetugasLab => 'operator',
            self::PenggunaJasa => 'pengguna_jasa',
        };
    }

    public function isStaff(): bool
    {
        return $this === self::AdminLab || $this === self::Superadmin;
    }
}
