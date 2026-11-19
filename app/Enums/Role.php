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
            self::Administrasi => 'Administrator',
            self::PetugasLab => 'Petugas Lab',
            self::PenggunaJasa => 'Pengguna Jasa',
        };
    }

    /** Admin Lab & Superadmin: verifikasi, pembayaran, penerbitan LHP. */
    public function isStaff(): bool
    {
        return $this === self::AdminLab || $this === self::Superadmin;
    }

    /**
     * Verifikasi pengajuan (boleh diuji / ditolak) dan konfirmasi pembayaran: Administrator, Admin Lab, Superadmin
     * (siapa pun yang lebih dulu). Administrator juga memeriksa isian lab & LHP sebelum terbit.
     */
    public function bolehVerifikasi(): bool
    {
        return $this === self::Administrasi || $this->isStaff();
    }
}
