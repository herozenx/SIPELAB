<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Master parameter uji (FPPCU): kode, metode acuan, persyaratan mutu bawaan, tarif per pengujian. */
class ParameterUji extends Model
{
    protected $table = 'parameter_uji';

    protected $fillable = ['kode', 'nama', 'bidang', 'lab_id', 'metode', 'persyaratan', 'satuan', 'keterangan', 'tarif', 'akreditasi', 'aktif', 'urutan'];

    protected function casts(): array
    {
        return ['tarif' => 'integer', 'akreditasi' => 'boolean', 'aktif' => 'boolean', 'urutan' => 'integer'];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('parameter_uji.aktif', true)
            ->whereHas('lab', fn ($l) => $l->where('aktif', true))
            ->orderBy('urutan')->orderBy('id');
    }

    public function labelLengkap(): string
    {
        return $this->nama.' ('.$this->metode.')'.($this->akreditasi ? '' : ' *');
    }
}
