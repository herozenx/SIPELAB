<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\Status;
use App\Models\Permohonan;
use App\Models\User;
use App\Services\Workflow;

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

    public function kelola(User $u, Permohonan $p): bool
    {
        return $u->isStaff();
    }

    public function inputHasil(User $u, Permohonan $p): bool
    {
        if ($p->status !== Status::DiLab || ! $p->current_lab_id) {
            return false;
        }

        // Admin Lab = kepala tim operator: boleh mengisi di lab mana pun sampel sedang berada.
        return $u->role === Role::AdminLab || ($u->role === Role::PetugasLab && $p->current_lab_id === $u->lab_id);
    }

    /**
     * Pengguna jasa hanya bisa membuka/mengunduh LHU setelah status SELESAI
     * (eksternal: setelah pelunasan dikonfirmasi). Admin bisa melihat LHU begitu terbit.
     */
    public function lihatLhu(User $u, Permohonan $p): bool
    {
        if ($u->role === Role::PetugasLab || ! $this->view($u, $p)) {
            return false;
        }
        if ($u->isStaff() || $u->role === Role::Administrasi) {
            return $p->lhu?->nomor !== null;
        }

        return $p->status === Status::Selesai;
    }

    /** Administrasi memeriksa isian operator & file LHP sebelum diterbitkan. */
    public function cek(User $u, Permohonan $p): bool
    {
        return in_array($u->role, [Role::Administrasi, Role::Superadmin], true) && $p->status === Status::MenungguCekAdministrasi;
    }

    /**
     * Unduh file Word LHP.
     * - Administrasi/Admin/Superadmin: draf sejak masuk pemeriksaan Administrasi, dan LHP terbit.
     * - Pengguna jasa: hanya LHP terbit yang sudah boleh diambil (status SELESAI).
     * - Petugas lab: tidak (uji independen).
     */
    public function unduhLhp(User $u, Permohonan $p): bool
    {
        if ($u->role === Role::PetugasLab || ! $this->view($u, $p)) {
            return false;
        }
        if ($u->role === Role::PenggunaJasa) {
            return $p->status === Status::Selesai && $p->lhu?->nomor !== null;
        }

        return $p->lhu?->nomor !== null
            || in_array($p->status, [Status::MenungguCekAdministrasi, Status::MenungguValidasi], true);
    }
}
