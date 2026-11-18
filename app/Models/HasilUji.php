<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilUji extends Model
{
    protected $table = 'hasil_uji';

    protected $guarded = ['id'];

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    /** Ringkasan per lab: MEMENUHI | MELEBIHI | TANPA_UJI (data lama: NEGATIF | POSITIF). */
    public const MEMENUHI = 'MEMENUHI';
    public const MELEBIHI = 'MELEBIHI';
    public const TANPA_UJI = 'TANPA_UJI';

    public function positif(): bool
    {
        return in_array($this->hasil, ['POSITIF', self::MELEBIHI], true);
    }

    public function label(): string
    {
        return match ($this->hasil) {
            'POSITIF' => 'Positif',
            'NEGATIF' => 'Negatif',
            self::MELEBIHI => 'Ada yang melebihi persyaratan',
            self::TANPA_UJI => 'Tidak ada uji di lab ini',
            default => 'Sudah diuji',
        };
    }

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class);
    }
}
