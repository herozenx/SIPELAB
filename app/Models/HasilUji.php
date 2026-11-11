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

    public function positif(): bool
    {
        return $this->hasil === 'POSITIF';
    }
}
