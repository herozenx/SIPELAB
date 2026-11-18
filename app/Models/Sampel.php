<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu sampel dalam permohonan (satu baris Buku Sampel, satu halaman LHP). */
class Sampel extends Model
{
    protected $table = 'sampel';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['urut' => 'integer'];
    }

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class);
    }

    public function hasil(): HasMany
    {
        return $this->hasMany(HasilParameter::class)->orderBy('urutan')->orderBy('id');
    }
}
