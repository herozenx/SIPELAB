<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Surat tugas pengujian untuk satu bidang/lab, ditujukan kepada Penyelia bidang tersebut. */
class SuratTugas extends Model
{
    protected $table = 'surat_tugas';

    protected $guarded = ['id'];

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class);
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function penyelia(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penyelia_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
