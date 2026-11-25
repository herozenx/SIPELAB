<?php

namespace App\Models;

use App\Enums\JenisBayar;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** Dianggap online bila ada aktivitas dalam N menit terakhir. */
    public const ONLINE_MENIT = 2;

    protected $fillable = ['nama', 'email', 'password', 'role', 'lab_id', 'instansi', 'alamat', 'tipe_pengguna', 'telepon', 'nip', 'nik', 'induk_id', 'aktif', 'wajib_ganti_password'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['aktif' => true, 'wajib_ganti_password' => false, 'auth_version' => 0, 'failed_attempts' => 0];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'tipe_pengguna' => JenisBayar::class,
            'aktif' => 'boolean',
            'wajib_ganti_password' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function permohonan(): HasMany
    {
        return $this->hasMany(Permohonan::class);
    }

    /** Akun operator perusahaan: induknya admin perusahaan (pengguna jasa yang mendaftar). */
    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'induk_id');
    }

    /** Operator (personel yang dihubungi) milik admin perusahaan ini. */
    public function operatorPerusahaan(): HasMany
    {
        return $this->hasMany(self::class, 'induk_id')->orderBy('nama');
    }

    /** Admin perusahaan = pengguna jasa tanpa induk; boleh menambah/menonaktifkan operator. */
    public function adminPerusahaan(): bool
    {
        return $this->role === Role::PenggunaJasa && $this->induk_id === null;
    }

    /** Id admin perusahaan tempat akun ini bernaung (dirinya sendiri bila admin). */
    public function perusahaanId(): int
    {
        return (int) ($this->induk_id ?? $this->id);
    }

    /** Semua akun dalam perusahaan yang sama: permohonan mereka saling terlihat. */
    public function scopeSePerusahaan(Builder $q, self $u): Builder
    {
        $id = $u->perusahaanId();

        return $q->where(fn ($w) => $w->where('id', $id)->orWhere('induk_id', $id));
    }

    /** Akun ini satu perusahaan dengan pemilik user_id tersebut. */
    public function sePerusahaanDengan(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }
        if ($userId === $this->id) {
            return true;
        }
        $lain = self::query()->whereKey($userId)->first(['id', 'induk_id']);

        return $lain !== null && (int) ($lain->induk_id ?? $lain->id) === $this->perusahaanId();
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    /** Admin, superadmin, dan operator lab: pihak Balai yang boleh membalas chat. */
    public function isPetugasBalai(): bool
    {
        return $this->role !== Role::PenggunaJasa;
    }

    public function jenisBayar(): JenisBayar
    {
        // Dibaca mentah agar aman untuk model yang belum memuat kolom ini (mode ketat Eloquent).
        $v = $this->getAttributes()['tipe_pengguna'] ?? null;

        return $v instanceof JenisBayar ? $v : (JenisBayar::tryFrom((string) $v) ?? JenisBayar::Mandiri);
    }

    public function online(): bool
    {
        $v = $this->getAttributes()['last_seen_at'] ?? null;

        return $v !== null && \Illuminate\Support\Carbon::parse($v)->gte(now()->subMinutes(self::ONLINE_MENIT));
    }

    /** Label singkat untuk chat & daftar online, mis. "Penyelia Mikrobiologi". */
    public function jabatan(): string
    {
        return $this->role->label().($this->lab ? ' '.$this->lab->nama : '');
    }

    public function isLab(): bool
    {
        return $this->role->isLab();
    }

    public function scopeOnline(Builder $q): Builder
    {
        return $q->where('aktif', true)->where('last_seen_at', '>=', now()->subMinutes(self::ONLINE_MENIT));
    }

    public function scopePetugasBalai(Builder $q): Builder
    {
        return $q->where('role', '!=', Role::PenggunaJasa->value);
    }

    /**
     * Cabut semua sesi aktif pengguna ini (ganti/reset sandi, nonaktif, ubah peran).
     * Versi dinaikkan agar sesi yang masih menyimpan versi lama ditolak middleware.
     */
    public function revokeSessions(): void
    {
        $this->increment('auth_version');
        \DB::table('sessions')->where('user_id', $this->id)->delete();
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->nama)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }
}
