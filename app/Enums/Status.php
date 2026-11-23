<?php

namespace App\Enums;

/**
 * Alur permohonan.
 * Mandiri  : Diajukan -> Diterima (registrasi sampel) -> Menunggu billing -> Menunggu pembayaran -> Cek pembayaran -> Siap uji (surat tugas)
 *            -> Di lab -> Pemeriksaan -> Paraf penyelia -> Pengesahan -> Selesai.
 * Lainnya  : Diajukan -> Diterima -> Siap uji -> Di lab -> Pemeriksaan -> Paraf -> Pengesahan
 *            -> Menunggu billing -> Menunggu pembayaran -> Cek pembayaran -> Selesai (bayar di belakang).
 */
enum Status: string
{
    case Diajukan = 'DIAJUKAN';
    case Ditolak = 'DITOLAK';
    case Diterima = 'DITERIMA';
    case MenungguBilling = 'MENUNGGU_BILLING';
    case MenungguPembayaran = 'MENUNGGU_PEMBAYARAN';
    case MenungguKonfirmasiBayar = 'MENUNGGU_KONFIRMASI_BAYAR';
    case SiapUji = 'SIAP_UJI';
    case DiLab = 'DI_LAB';
    case MenungguPemeriksaan = 'MENUNGGU_PEMERIKSAAN';
    case MenungguParaf = 'MENUNGGU_PARAF';
    case MenungguPengesahan = 'MENUNGGU_PENGESAHAN';
    case Selesai = 'SELESAI';

    /** Label untuk petugas Balai. */
    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Menunggu verifikasi',
            self::Ditolak => 'Ditolak',
            self::Diterima => 'Menunggu registrasi sampel',
            self::MenungguBilling => 'Menunggu kode billing',
            self::MenungguPembayaran => 'Menunggu pembayaran',
            self::MenungguKonfirmasiBayar => 'Cek pembayaran',
            self::SiapUji => 'Menunggu surat tugas',
            self::DiLab => 'Sedang diuji',
            self::MenungguPemeriksaan => 'Validasi hasil',
            self::MenungguParaf => 'Menunggu paraf penyelia',
            self::MenungguPengesahan => 'Validasi akhir',
            self::Selesai => 'LHP selesai',
        };
    }

    /** Label untuk pengguna jasa: tahapan internal lab diringkas menjadi "Sedang diproses/pengujian". */
    public function labelPengguna(): string
    {
        return match ($this) {
            self::Diajukan => 'Menunggu verifikasi',
            self::Ditolak => 'Ditolak',
            self::Diterima => 'Diterima, serahkan sampel',
            self::MenungguBilling => 'Menunggu kode billing',
            self::MenungguPembayaran => 'Menunggu pembayaran',
            self::MenungguKonfirmasiBayar => 'Pembayaran sedang dicek',
            self::SiapUji, self::DiLab => 'Sedang diproses/pengujian',
            self::MenungguPemeriksaan, self::MenungguParaf, self::MenungguPengesahan => 'Penyusunan LHP',
            self::Selesai => 'LHP selesai',
        };
    }

    /** Nada warna badge: info | wait | lab | ok | bad */
    public function tone(): string
    {
        return match ($this) {
            self::Diajukan, self::Diterima, self::MenungguBilling, self::MenungguKonfirmasiBayar,
            self::MenungguPemeriksaan, self::MenungguParaf, self::MenungguPengesahan => 'wait',
            self::MenungguPembayaran => 'info',
            self::SiapUji, self::DiLab => 'lab',
            self::Selesai => 'ok',
            self::Ditolak => 'bad',
        };
    }

    /** Tahap penyusunan LHP: hasil sudah lengkap, belum disahkan. Proses bisa dikembalikan ke lab. */
    public function tahapLhp(): bool
    {
        return in_array($this, [self::MenungguPemeriksaan, self::MenungguParaf, self::MenungguPengesahan], true);
    }

    public function tahapBayar(): bool
    {
        return in_array($this, [self::MenungguBilling, self::MenungguPembayaran, self::MenungguKonfirmasiBayar], true);
    }
}
