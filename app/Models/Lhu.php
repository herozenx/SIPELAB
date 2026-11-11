<?php

namespace App\Models;

use App\Enums\StatusIzin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lhu extends Model
{
    protected $table = 'lhu';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status_izin' => StatusIzin::class, 'validated_at' => 'datetime'];
    }

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
