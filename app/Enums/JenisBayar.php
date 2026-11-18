<?php

namespace App\Enums;

/**
 * Jenis layanan / pengguna jasa. Menentukan kode sampel (Buku Sampel), cara bayar, dan isian LHP.
 * - MANDIRI    (SP.M) : bayar di awal (setelah sampel diverifikasi), baru diuji.
 * - SURVEILEN  (SP.S) : sementara sama dengan Mandiri (bayar di awal).
 * - EKSTERNAL  (SP.PE): sampel langsung diuji; bayar setelah LHP jadi, LHP diambil setelah lunas.
 * - MONITORING (SP.MN), TEST RESULT (SP.E), UJI BANDING (SP.UB): sampel internal Balai, dicatat Admin, tanpa tagihan.
 */
enum JenisBayar: string
{
    case Mandiri = 'MANDIRI';
    case Eksternal = 'EKSTERNAL';
    case Surveilen = 'SURVEILEN';
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

    public function keterangan(): string
    {
        return match (true) {
            $this->internal() => 'Sampel internal Balai, tanpa tagihan',
            $this->bayarDiAwal() => 'Bayar di awal, sebelum sampel diuji',
            default => 'Bayar setelah LHP jadi; LHP diambil setelah lunas',
        };
    }

    /** Kode layanan pada kode sampel Buku Sampel: SP.{kode}/ddmmyy.NN */
    public function kodeSampel(): string
    {
        return match ($this) {
            self::Mandiri => 'M',
            self::Eksternal => 'PE',
            self::Surveilen => 'S',
            self::Monitoring => 'MN',
            self::TestResult => 'E',
            self::UjiBanding => 'UB',
        };
    }

    /** true = tagihan dibayar sebelum sampel diuji (Mandiri, Surveilen). */
    public function bayarDiAwal(): bool
    {
        return $this === self::Mandiri || $this === self::Surveilen;
    }

    /** Sampel internal Balai: dicatat Admin, tanpa pengguna jasa & tanpa tagihan. */
    public function internal(): bool
    {
        return in_array($this, [self::Monitoring, self::TestResult, self::UjiBanding], true);
    }

    /**
     * Catatan nomor 1 pada LHP: kalimat yang TIDAK dicoret.
     * Sesuai LHP asli: Surveilen & Test Result "mewakili populasi", layanan lain "hanya berlaku untuk contoh uji".
     */
    public function mewakiliPopulasi(): bool
    {
        return $this === self::Surveilen || $this === self::TestResult;
    }

    /** Kunci pengaturan format nomor LHP layanan ini. */
    public function kunciNomor(): string
    {
        return 'nomor_lhp_'.strtolower($this->value);
    }

    /** Lab eksternal tempat uji subkontrak (kolom EKSTERNAL di Buku Sampel). */
    public const SUBKONTRAK = ['Angler', 'LPPMHP', 'Saraswati'];

    /** @return self[] layanan yang dipilih pengguna jasa saat mengajukan */
    public static function publik(): array
    {
        return [self::Mandiri, self::Eksternal, self::Surveilen];
    }

    /** @return self[] */
    public static function internalCases(): array
    {
        return [self::Monitoring, self::TestResult, self::UjiBanding];
    }
}
