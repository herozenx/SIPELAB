<?php

namespace App\Models;

use App\Enums\JenisBayar;
use App\Enums\Status;
use App\Enums\Tujuan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Permohonan extends Model
{
    protected $table = 'permohonan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'tujuan' => Tujuan::class,
            'jenis_bayar' => JenisBayar::class,
            'jumlah_sampel' => 'integer',
            'tagihan' => 'integer',
            'masuk_lab_at' => 'datetime',
            'target_selesai_at' => 'datetime',
        ];
    }

    public function pemohon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function labSaatIni(): BelongsTo
    {
        return $this->belongsTo(Lab::class, 'current_lab_id');
    }

    public function hasil(): HasMany
    {
        return $this->hasMany(HasilUji::class);
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(Dokumen::class);
    }

    public function lhu(): HasOne
    {
        return $this->hasOne(Lhu::class);
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(StatusLog::class)->orderBy('id');
    }

    /** Tahapan pengujian per lab (norma waktu & operator yang menangani). */
    public function tahap(): HasMany
    {
        return $this->hasMany(TahapLab::class)->orderBy('id');
    }

    /** Tahap di lab tempat sampel berada sekarang. */
    public function tahapAktif(): HasOne
    {
        return $this->hasOne(TahapLab::class)->whereNull('selesai_at')->latestOfMany();
    }

    public function eksternal(): bool
    {
        return $this->jenis_bayar === JenisBayar::Eksternal;
    }

    /** Eksternal yang LHU-nya sudah jadi tetapi belum lunas. */
    public function menungguPelunasan(): bool
    {
        return $this->eksternal() && in_array($this->status, [Status::MenungguPembayaran, Status::MenungguKonfirmasiBayar], true);
    }
}
