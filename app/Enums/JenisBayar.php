<?php

namespace App\Enums;

/**
 * Jenis layanan = "Tujuan permohonan" (masukan Balai 25 Nov 2026).
 * - Pengguna jasa memilih saat mengajukan: Ekspor (Test Result), Uji Mandiri, Uji Banding.
 * - Petugas (Operator/Admin Lab) dapat menetapkan saat registrasi: Surveilen, Monitoring, Uji Profisiensi, Pengujian Eksternal.
 * Layanan pengguna jasa dibayar di awal (billing SIMPONI sebelum diuji); layanan petugas dibayar di belakang.
 */
enum JenisBayar: string
{
    case TestResult = 'TEST_RESULT';
    case Mandiri = 'MANDIRI';
    case UjiBanding = 'UJI_BANDING';
    case Surveilen = 'SURVEILEN';
    case Monitoring = 'MONITORING';
    case UjiProfisiensi = 'UJI_PROFISIENSI';
    case Eksternal = 'EKSTERNAL';

    public function label(): string
    {
        return match ($this) {
            self::TestResult => 'Ekspor (Test Result)',
            self::Mandiri => 'Uji Mandiri',
            self::UjiBanding => 'Uji Banding',
            self::Surveilen => 'Surveilen',
            self::Monitoring => 'Monitoring',
            self::UjiProfisiensi => 'Uji Profisiensi',
            self::Eksternal => 'Pengujian Eksternal',
        };
    }

    /** @return self[] pilihan pengguna jasa saat mengajukan permohonan. */
    public static function pilihanPengguna(): array
    {
        return [self::TestResult, self::Mandiri, self::UjiBanding];
    }

    /** @return self[] pilihan khusus petugas (Operator/Admin Lab). */
    public static function pilihanPetugas(): array
    {
        return [self::Surveilen, self::Monitoring, self::UjiProfisiensi, self::Eksternal];
    }

    /** @return self[] semua jenis yang bisa ditetapkan petugas saat registrasi. */
    public static function pilihan(): array
    {
        return [...self::pilihanPengguna(), ...self::pilihanPetugas()];
    }

    /**
     * Awalan kode sampel (SP = surat pengajuan) sesuai buku sampel lab, mis. SP.E/251126.01.
     * Terkonfirmasi dari dokumen Balai: E, M, S, PE. Lainnya sementara, menunggu konfirmasi Balai.
     */
    public function kodeSurat(): string
    {
        return match ($this) {
            self::TestResult => 'SP.E',
            self::Mandiri => 'SP.M',
            self::UjiBanding => 'SP.UB',
            self::Surveilen => 'SP.S',
            self::Monitoring => 'SP.MN',
            self::UjiProfisiensi => 'SP.UP',
            self::Eksternal => 'SP.PE',
        };
    }

    public function keterangan(): string
    {
        return $this->bayarDiAwal()
            ? 'Bayar di awal, sebelum sampel diuji'
            : 'Dibayar di belakang, setelah LHP disahkan';
    }

    /** Penjelasan singkat di form pengajuan. */
    public function penjelasan(): string
    {
        return match ($this) {
            self::TestResult => 'Hasil uji untuk keperluan ekspor',
            self::Mandiri => 'Pengujian atas kemauan sendiri',
            self::UjiBanding => 'Pembanding hasil uji laboratorium lain',
            default => $this->keterangan(),
        };
    }

    /** true = tagihan dibayar sebelum sampel diuji (layanan yang diajukan pengguna jasa). */
    public function bayarDiAwal(): bool
    {
        return in_array($this, self::pilihanPengguna(), true);
    }

    /** Tujuan distribusi yang dicetak di LHP mengikuti jenis layanan. */
    public function tujuan(): Tujuan
    {
        return $this === self::TestResult ? Tujuan::Ekspor : Tujuan::Domestik;
    }
}
