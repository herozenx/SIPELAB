<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Parameter yang diminta pada satu permohonan, beserta jumlah pengujian dan hasilnya (masuk ke LHP). */
class PermohonanParameter extends Model
{
    protected $table = 'permohonan_parameter';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer', 'tarif' => 'integer', 'akreditasi' => 'boolean',
            'memenuhi' => 'boolean', 'diinput_at' => 'datetime',
        ];
    }

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class);
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function penginput(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diinput_oleh');
    }

    public function sudahDiisi(): bool
    {
        return $this->hasil !== null && $this->memenuhi !== null;
    }

    public function subtotal(): int
    {
        return $this->tarif * $this->jumlah;
    }
}
