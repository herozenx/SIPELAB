<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Perhitungan norma waktu dalam HARI KERJA (Senin–Jumat, di luar hari libur
 * yang diisi Superadmin di menu Pengaturan).
 */
class HariKerja
{
    /** @var array<string, true>|null */
    private static ?array $libur = null;

    /** @return array<string, true> tanggal Y-m-d */
    public static function libur(): array
    {
        if (self::$libur === null) {
            self::$libur = [];
            foreach (preg_split('/[\s,;]+/', Setting::get('hari_libur'), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $tgl) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) {
                    self::$libur[$tgl] = true;
                }
            }
        }

        return self::$libur;
    }

    /** Dipanggil setelah pengaturan hari libur berubah (dan di pengujian). */
    public static function lupakan(): void
    {
        self::$libur = null;
    }

    public static function kerja(CarbonInterface $d): bool
    {
        return ! $d->isWeekend() && ! isset(self::libur()[$d->format('Y-m-d')]);
    }

    private static function lokal(CarbonInterface $d): Carbon
    {
        return Carbon::instance($d)->setTimezone(config('app.timezone'));
    }

    /**
     * Batas waktu = akhir hari kerja ke-N setelah tanggal mulai.
     * Contoh: masuk Jumat, norma 1 hari kerja => batas Senin 23.59.
     */
    public static function batas(CarbonInterface $mulai, int $hari): Carbon
    {
        $d = self::lokal($mulai)->startOfDay();
        for ($n = 0; $n < $hari;) {
            $d->addDay();
            if (self::kerja($d)) {
                $n++;
            }
        }

        return $d->endOfDay();
    }

    /** Jumlah hari kerja di antara dua tanggal: (dari, sampai], tanpa jam. */
    public static function antara(CarbonInterface $dari, CarbonInterface $sampai): int
    {
        $a = self::lokal($dari)->startOfDay();
        $b = self::lokal($sampai)->startOfDay();
        $n = 0;
        while ($a->lt($b)) {
            $a->addDay();
            if (self::kerja($a)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Status norma sebuah tahapan untuk ditampilkan sebagai badge.
     *
     * @return array{0:string, 1:string} [teks, nada badge: ok|info|wait|bad]
     */
    public static function status(CarbonInterface $batas, ?CarbonInterface $selesai = null, ?CarbonInterface $now = null): array
    {
        if ($selesai) {
            if ($selesai->lte($batas)) {
                return ['Tepat waktu', 'ok'];
            }

            return ['Terlambat '.max(1, self::antara($batas, $selesai)).' hari kerja', 'bad'];
        }
        $now ??= now();
        if ($now->gt($batas)) {
            return ['Lewat '.max(1, self::antara($batas, $now)).' hari kerja', 'bad'];
        }
        $sisa = self::antara($now, $batas);

        return match (true) {
            $sisa === 0 => ['Batas hari ini', 'wait'],
            $sisa === 1 => ['Sisa 1 hari kerja', 'wait'],
            default => ["Sisa {$sisa} hari kerja", 'info'],
        };
    }
}
