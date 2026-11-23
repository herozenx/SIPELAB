<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak pemeriksaan & paraf digital LHP:
 * PERIKSA (validasi hasil oleh Administrator), PARAF (Penyelia, satu per bidang), SAH (Penanggung Jawab).
 */
class Persetujuan extends Model
{
    public const PERIKSA = 'PERIKSA';
    public const PARAF = 'PARAF';
    public const SAH = 'SAH';

    public const UPDATED_AT = null;

    protected $table = 'persetujuan';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function label(): string
    {
        return match ($this->jenis) {
            self::PERIKSA => 'Divalidasi',
            self::PARAF => 'Diparaf',
            self::SAH => 'Disahkan',
            default => $this->jenis,
        };
    }
}
