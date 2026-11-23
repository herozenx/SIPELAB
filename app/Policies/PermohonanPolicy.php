<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\Status;
use App\Models\Permohonan;
use App\Models\User;
use App\Services\Workflow;

/**
 * Hak akses per tindakan, mengikuti flowchart alur SILANTRO:
 * verifikasi = Administrator, registrasi sampel = Operator/Admin Lab, billing = Operator Bendahara,
 * surat tugas = Admin Laboratorium, validasi hasil = Administrator, paraf = Penyelia, validasi akhir = Penanggung Jawab.
 * Administrator dapat menggantikan petugas administrasi bila berhalangan; paraf dan pengesahan tidak.
 */
class PermohonanPolicy
{
    public function __construct(private Workflow $wf) {}

    public function view(User $u, Permohonan $p): bool
    {
        return $this->wf->bolehLihat($u, $p);
    }

    public function create(User $u): bool
    {
        return $u->role === Role::PenggunaJasa;
    }

    public function bayar(User $u, Permohonan $p): bool
    {
        return $u->role === Role::PenggunaJasa && $p->user_id === $u->id;
    }

    /** Verifikasi berkas & data permohonan: Administrator. */
    public function verifikasi(User $u, Permohonan $p): bool
    {
        return $u->role === Role::Superadmin;
    }

    /** Penentuan jenis layanan & registrasi sampel: Operator dan Admin Laboratorium. */
    public function registrasi(User $u, Permohonan $p): bool
    {
        return $u->hasRole(Role::Operator, Role::AdminLab);
    }

    public function billing(User $u, Permohonan $p): bool
    {
        return $u->hasRole(Role::Bendahara, Role::Superadmin);
    }

    public function suratTugas(User $u, Permohonan $p): bool
    {
        return $u->hasRole(Role::AdminLab, Role::Superadmin);
    }

    public function inputHasil(User $u, Permohonan $p): bool
    {
        return $u->isLab() && $this->wf->tahapTerbuka($p, $u->lab_id) !== null;
    }

    public function periksa(User $u, Permohonan $p): bool
    {
        return $u->role === Role::Superadmin;
    }

    public function paraf(User $u, Permohonan $p): bool
    {
        return $u->role === Role::Penyelia && $p->status === Status::MenungguParaf
            && $p->parameter()->where('lab_id', $u->lab_id)->exists();
    }

    public function sahkan(User $u, Permohonan $p): bool
    {
        return $u->role === Role::PenanggungJawab;
    }

    public function kembalikan(User $u, Permohonan $p): bool
    {
        if ($u->role === Role::Penyelia) {
            return $this->view($u, $p);
        }

        return $u->hasRole(Role::Superadmin, Role::PenanggungJawab);
    }

    /** LHP: petugas kantor begitu disahkan; pengguna jasa pemilik setelah permohonan selesai (lunas). */
    public function lihatLhu(User $u, Permohonan $p): bool
    {
        if ($p->lhu?->nomor === null) {
            return false;
        }
        if ($u->role === Role::PenggunaJasa) {
            return $p->user_id === $u->id && $p->status === Status::Selesai;
        }

        return $u->role->bolehCetakLhp();
    }

    /** Bukti terima sampel: setelah sampel diregistrasi, untuk pemohon dan petugas kantor. */
    public function buktiTerima(User $u, Permohonan $p): bool
    {
        return $p->kode_sampel !== null && ($u->isStaff() || ($u->role === Role::PenggunaJasa && $p->user_id === $u->id));
    }

    public function cetakSuratTugas(User $u, Permohonan $p): bool
    {
        return $u->hasRole(Role::AdminLab, Role::Superadmin, Role::Operator)
            || ($u->isLab() && $this->view($u, $p));
    }
}
