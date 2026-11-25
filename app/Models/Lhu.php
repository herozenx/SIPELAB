<?php

namespace App\Models;

use App\Enums\StatusIzin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** LHP (Laporan Hasil Pengujian). Nama tabel/model tetap "lhu" agar data lama tetap terbaca. */
class Lhu extends Model
{
    protected $table = 'lhu';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status_izin' => StatusIzin::class, 'validated_at' => 'datetime',
            'tahun' => 'integer', 'nomor_urut' => 'integer', 'versi_sidik' => 'integer',
        ];
    }

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class);
    }

    /** Jabatan penanda tangan yang dipilih saat pengesahan (rapat 19 Nov): [Indonesia, Inggris]. */
    public const JABATAN = [
        'PJ' => ['Penanggung Jawab Laboratorium', 'Laboratory in Charge'],
        'KEPALA' => ['Kepala UPT', 'Head of TIU'],
    ];

    /** LHP lama (sebelum ada pilihan) tetap mencetak kedua jabatan seperti format Balai. */
    public function jabatan(): array
    {
        return self::JABATAN[$this->jabatan_penanda] ?? ['Kepala UPT/Penanggung jawab Laboratorium**)', 'Head of TIU/Laboratory in Charge**)'];
    }

    /** Pejabat yang mengesahkan (Penanggung Jawab Lab). */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
