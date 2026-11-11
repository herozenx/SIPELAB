<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dokumen extends Model
{
    public const PENDUKUNG = 'pendukung';
    public const BUKTI_BAYAR = 'bukti_bayar';
    public const LAMPIRAN_LAB = 'lampiran_lab';

    protected $table = 'dokumen';

    protected $guarded = ['id'];

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class);
    }

    public function jenisLabel(): string
    {
        return match ($this->jenis) {
            self::PENDUKUNG => 'Dokumen pendukung',
            self::BUKTI_BAYAR => 'Bukti bayar SIMPONI',
            self::LAMPIRAN_LAB => 'Lampiran lab',
            default => $this->jenis,
        };
    }
}
