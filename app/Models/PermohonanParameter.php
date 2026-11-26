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
            'memenuhi' => 'boolean', 'diinput_at' => 'datetime', 'lembar_kerja' => 'array',
        ];
    }

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class);
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(PermohonanProduk::class, 'produk_id');
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    /** Analis yang ditugaskan penyelia (disposisi). */
    public function analis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analis_id');
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
