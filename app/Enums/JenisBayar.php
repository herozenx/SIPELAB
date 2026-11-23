<?php

namespace App\Enums;

/**
 * Jenis layanan (jenis pengguna jasa). Pengguna jasa hanya bisa mengajukan Mandiri;
 * Eksternal (Pengujian Eksternal) dan Surveilen ditetapkan Operator saat verifikasi.
 * - MANDIRI  : bayar di awal (billing SIMPONI oleh Operator Bendahara), baru diuji.
 * - EKSTERNAL/SURVEILEN: sampel langsung diuji; dibayar di belakang setelah LHP disahkan.
 */
enum JenisBayar: string
{
    case Mandiri = 'MANDIRI';
    case Eksternal = 'EKSTERNAL';
    case Surveilen = 'SURVEILEN';
    // Data lama dari versi sebelumnya (branch sistem); tetap terbaca, tidak bisa dipilih lagi.
    case Monitoring = 'MONITORING';
    case TestResult = 'TEST_RESULT';
    case UjiBanding = 'UJI_BANDING';

    public function label(): string
    {
        return match ($this) {
            self::Mandiri => 'Mandiri',
            self::Eksternal => 'Pengujian Eksternal',
            self::Surveilen => 'Surveilen',
            self::Monitoring => 'Monitoring',
            self::TestResult => 'Test Result',
            self::UjiBanding => 'Uji Banding',
        };
    }

    /** @return self[] jenis layanan yang bisa ditetapkan saat registrasi (sesuai keputusan: 3 jenis). */
    public static function pilihan(): array
    {
        return [self::Mandiri, self::Eksternal, self::Surveilen];
    }

    /** Awalan kode sampel (SP = surat pengajuan) sesuai buku sampel lab, mis. SP.M/221126.01. */
    public function kodeSurat(): string
    {
        return match ($this) {
            self::Mandiri => 'SP.M',
            self::Eksternal => 'SP.PE',
            self::Surveilen => 'SP.S',
            self::Monitoring => 'SP.MN',
            self::TestResult => 'SP.E',
            self::UjiBanding => 'SP.UB',
        };
    }

    public function keterangan(): string
    {
        return $this->bayarDiAwal()
            ? 'Bayar di awal, sebelum sampel diuji'
            : 'Dibayar di belakang, setelah LHP disahkan';
    }

    /** true = tagihan dibayar sebelum sampel diuji (hanya Mandiri). */
    public function bayarDiAwal(): bool
    {
        return $this === self::Mandiri;
    }
}
