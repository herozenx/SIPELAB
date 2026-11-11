<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lab extends Model
{
    protected $fillable = ['nama', 'urutan', 'tarif', 'norma_hari', 'aktif'];

    protected $attributes = ['norma_hari' => 2];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'urutan' => 'integer', 'tarif' => 'integer', 'norma_hari' => 'integer'];
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif', true)->orderBy('urutan');
    }

    /** Operator (petugas) yang ditempatkan di tim lab ini. */
    public function operator(): HasMany
    {
        return $this->hasMany(User::class)->where('role', Role::PetugasLab->value)->orderBy('nama');
    }
}
