<?php

namespace App\Enums;

enum Role: string
{
    case Superadmin = 'superadmin';
    case AdminLab = 'admin_lab';
    case Admin = 'admin';
    case Administrasi = 'administrasi';
    case PetugasLab = 'petugas_lab';
    case PenggunaJasa = 'pengguna_jasa';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::AdminLab => 'Admin Lab',
            self::Admin => 'Admin',
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

    /** Boleh memverifikasi pengajuan (boleh diuji / ditolak) dan mengonfirmasi pembayaran: Admin, Admin Lab, Superadmin. */
    public function bolehVerifikasi(): bool
    {
        return $this === self::Admin || $this->isStaff();
    }
}
