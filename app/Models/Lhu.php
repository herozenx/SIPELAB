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
        return ['status_izin' => StatusIzin::class, 'validated_at' => 'datetime', 'cek_at' => 'datetime'];
    }

    public const JABATAN_KEPALA = 'KEPALA';
    public const JABATAN_PJ = 'PJ';

    /** Petugas Administrasi yang memeriksa isian operator dan file LHP. */
    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cek_by');
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
