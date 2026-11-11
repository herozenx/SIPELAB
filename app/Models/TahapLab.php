<?php

namespace App\Models;

use App\Support\HariKerja;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Satu tahapan pengujian sampel di satu lab: kapan masuk, batas norma, kapan selesai, dan siapa yang menangani. */
class TahapLab extends Model
{
    protected $table = 'tahap_lab';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'norma_hari' => 'integer',
            'masuk_at' => 'datetime',
            'batas_at' => 'datetime',
            'selesai_at' => 'datetime',
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

    /** Operator (petugas lab) yang menangani sampel pada tahap ini. */
    public function operator(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tahap_operator', 'tahap_lab_id', 'user_id')
            ->withPivot('created_at')->orderBy('tahap_operator.id');
    }

    /** @return array{0:string, 1:string} */
    public function norma(): array
    {
        return HariKerja::status($this->batas_at, $this->selesai_at);
    }
}
