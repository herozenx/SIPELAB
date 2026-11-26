<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proses yang dikembalikan ke lab sebelum LHP disahkan.
 * REVISI   : kesalahan input/data; hasil lama tetap ada untuk diperbaiki analis.
 * UJI_ULANG: sampel diuji ulang; hasil lama dikosongkan (disimpan di data_lama) dan norma waktu dihitung ulang.
 * PERBAIKAN: paraf/validasi akhir tidak disetujui, data diperbaiki lewat validasi hasil oleh Administrator (tanpa lab).
 */
class Pengembalian extends Model
{
    public const REVISI = 'REVISI';
    public const UJI_ULANG = 'UJI_ULANG';
    public const PERBAIKAN = 'PERBAIKAN';

    public const UPDATED_AT = null;

    protected $table = 'pengembalian';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['selesai_at' => 'datetime', 'data_lama' => 'array'];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh');
    }

    public function label(): string
    {
        return self::labelJenis($this->jenis);
    }

    /**
     * Kode sampel untuk uji ulang di lab, mengikuti rekap uji ulang Balai: kode asli + "-1", lalu "-2" bila diuji ulang lagi.
     * Hanya untuk catatan lab (agenda, riwayat); LHP tetap memakai kode asli.
     * $sampai: hitung sampai pengembalian ini saja (untuk riwayat).
     */
    public static function kodeUjiUlang(string $kode, iterable $pengembalian, ?int $labId, ?self $sampai = null): string
    {
        // Uji ulang pertama "-1", berikutnya "-2", dan seterusnya.
        $n = collect($pengembalian)->filter(fn ($k) => $k->jenis === self::UJI_ULANG && $k->lab_id === $labId
            && ($sampai === null || $k->id <= $sampai->id))->count();

        return $n > 0 ? "{$kode}-{$n}" : $kode;
    }

    public static function labelJenis(string $jenis): string
    {
        return match ($jenis) {
            self::UJI_ULANG => 'Uji ulang sampel',
            self::PERBAIKAN => 'Perbaikan data',
            default => 'Revisi data',
        };
    }
}
