<?php

namespace App\Models;

use App\Enums\Role;
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

    /**
     * Pilihan penanda tangan surat tugas: nama di Pengaturan, lalu akun Penanggung Jawab Lab yang aktif.
     *
     * @return array<string, array{nama: string, nip: string}>
     */
    public static function pilihanPenanda(): array
    {
        $pilihan = [];
        if (($nama = trim(Setting::get('nama_kepala_lab'))) !== '') {
            $pilihan['setelan'] = ['nama' => $nama, 'nip' => trim(Setting::get('nip_kepala_lab'))];
        }
        $pj = User::query()->where('role', Role::PenanggungJawab->value)->where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'nip']);
        foreach ($pj as $u) {
            if (! collect($pilihan)->contains(fn ($x) => $x['nama'] === $u->nama)) {
                $pilihan['u'.$u->id] = ['nama' => $u->nama, 'nip' => (string) $u->nip];
            }
        }

        return $pilihan;
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
