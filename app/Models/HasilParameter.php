<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hasil satu parameter pada satu sampel, persis seperti diisi operator.
 * Teks bidang/nama/persyaratan/metode disalin dari katalog saat input supaya LHP yang sudah terbit tidak ikut berubah.
 */
class HasilParameter extends Model
{
    protected $table = 'hasil_parameter';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['ulangan' => 'array', 'rincian' => 'array', 'melebihi' => 'boolean', 'urutan' => 'integer'];
    }

    public function sampel(): BelongsTo
    {
        return $this->belongsTo(Sampel::class);
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(ParameterUji::class, 'parameter_id');
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    public function namaPolos(): string
    {
        return str_replace('_', '', $this->nama);
    }
}
