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
            'diterima_at' => 'datetime',
            'billing_at' => 'datetime',
            'billing_kedaluwarsa_at' => 'datetime',
            'dibayar_at' => 'datetime',
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

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterima_oleh');
    }

    /** Parameter yang diminta beserta hasil ujinya. */
    public function parameter(): HasMany
    {
        return $this->hasMany(PermohonanParameter::class)->orderBy('id');
    }

    /** Hasil uji format lama (satu hasil per lab), dipakai LHU yang terbit sebelum revisi 2. */
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

    /** Tahapan pengujian per lab (norma waktu & analis yang menangani). */
    public function tahap(): HasMany
    {
        return $this->hasMany(TahapLab::class)->orderBy('id');
    }

    public function suratTugas(): HasMany
    {
        return $this->hasMany(SuratTugas::class)->orderBy('id');
    }

    public function persetujuan(): HasMany
    {
        return $this->hasMany(Persetujuan::class)->orderBy('id');
    }

    public function pengembalian(): HasMany
    {
        return $this->hasMany(Pengembalian::class)->orderBy('id');
    }

    /** Tahap di lab yang masih berjalan (salah satunya, untuk tampilan ringkas). */
    public function tahapAktif(): HasOne
    {
        return $this->hasOne(TahapLab::class)->whereNull('selesai_at')->latestOfMany();
    }

    /** Kode yang dikenal petugas: kode sampel bila sudah diregistrasi, selain itu nomor permohonan. */
    public function kodeTampil(): string
    {
        return $this->kode_sampel ?: (string) $this->kode;
    }

    public function mandiri(): bool
    {
        return ($this->jenis_bayar ?? JenisBayar::Mandiri) === JenisBayar::Mandiri;
    }

    /** Selain Mandiri dibayar di belakang (setelah LHP disahkan). */
    public function eksternal(): bool
    {
        return ! $this->mandiri();
    }

    public function lhpTerbit(): bool
    {
        return $this->lhu?->nomor !== null;
    }

    /** Tahap pembayaran setelah LHP disahkan (layanan non-Mandiri). */
    public function menungguPelunasan(): bool
    {
        return $this->status->tahapBayar() && $this->lhpTerbit();
    }

    /** @return \Illuminate\Support\Collection<int, int> id lab yang terlibat (punya parameter). */
    public function labTerlibat()
    {
        return $this->parameter->pluck('lab_id')->unique()->values();
    }
}
