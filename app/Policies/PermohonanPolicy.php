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
        return $u->role === Role::PenggunaJasa && $u->sePerusahaanDengan($p->user_id);
    }

    /** Verifikasi + kaji ulang permintaan: Administrator (dan Super Admin) serta Admin Laboratorium (masukan Balai 25 Nov). */
    public function verifikasi(User $u, Permohonan $p): bool
    {
        return $u->hasRole(Role::Superadmin, Role::Administrasi, Role::AdminLab);
    }

    /** Penentuan jenis layanan & registrasi sampel: Operator dan Admin Laboratorium. */
    public function registrasi(User $u, Permohonan $p): bool
    {
        return $u->hasRole(Role::Operator, Role::AdminLab);
    }

    public function billing(User $u, Permohonan $p): bool
    {
        return $u->hasRole(Role::Bendahara, Role::Superadmin, Role::Administrasi);
    }

    public function suratTugas(User $u, Permohonan $p): bool
    {
        return $u->hasRole(Role::AdminLab, Role::Superadmin, Role::Administrasi);
    }

    public function inputHasil(User $u, Permohonan $p): bool
    {
        return $u->isLab() && $this->wf->tahapTerbuka($p, $u->lab_id) !== null;
    }

    public function periksa(User $u, Permohonan $p): bool
    {
        return $u->hasRole(Role::Superadmin, Role::Administrasi);
    }

    public function paraf(User $u, Permohonan $p): bool
    {
        return $u->role === Role::Penyelia && $p->status === Status::MenungguParaf
            && $p->parameter()->where('lab_id', $u->lab_id)->exists();
    }

    /** Paraf Admin Laboratorium setelah validasi Administrator (masukan Zoom 26 Nov). */
    public function parafAdmin(User $u, Permohonan $p): bool
    {
        return $u->role === Role::AdminLab && $p->status === Status::MenungguParafAdmin;
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

        // Admin Lab menolak hanya pada tahap parafnya sendiri.
        if ($u->role === Role::AdminLab) {
            return $p->status === Status::MenungguParafAdmin;
        }

        return $u->hasRole(Role::Superadmin, Role::Administrasi, Role::PenanggungJawab);
    }

    /** LHP: hanya petugas kantor. Pengguna jasa cukup melihat status selesai; LHP dicetak & diserahkan di kantor (rapat 19 Nov). */
    public function lihatLhu(User $u, Permohonan $p): bool
    {
        if ($p->lhu?->nomor === null) {
            return false;
        }
        return $u->role->bolehCetakLhp();
    }

    /** Bukti terima sampel: setelah sampel diregistrasi, untuk pemohon dan petugas kantor. */
    public function buktiTerima(User $u, Permohonan $p): bool
    {
        return $p->kode_sampel !== null && $p->diterima_at !== null && ($u->isStaff() || ($u->role === Role::PenggunaJasa && $u->sePerusahaanDengan($p->user_id)));
    }

    public function cetakSuratTugas(User $u, Permohonan $p): bool
    {
        return $u->hasRole(Role::AdminLab, Role::Superadmin, Role::Administrasi, Role::Operator)
            || ($u->isLab() && $this->view($u, $p));
    }
}
