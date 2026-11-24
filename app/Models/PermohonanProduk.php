<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Produk/jenis sampel dalam satu permohonan. Sesuai buku sampel: satu perusahaan yang menguji
 * beberapa produk di hari yang sama memakai satu kode (SP.x/DDMMYY.NN) dengan akhiran A, B, C...,
 * satu nomor agenda, dan satu nomor LHP (satu halaman per produk).
 */
class PermohonanProduk extends Model
{
    protected $table = 'permohonan_produk';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['urut' => 'integer', 'jumlah' => 'integer'];
    }

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class);
    }

    public function parameter(): HasMany
    {
        return $this->hasMany(PermohonanParameter::class, 'produk_id')->orderBy('id');
    }

    /** Akhiran kode produk ke-n bila permohonan berisi lebih dari satu produk: A, B, C, ... */
    public static function akhiran(int $urut): string
    {
        return chr(64 + $urut);
    }
}
