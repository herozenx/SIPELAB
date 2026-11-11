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
        return $u->role === Role::PetugasLab && $p->status === Status::DiLab && $p->current_lab_id === $u->lab_id;
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
        if ($u->isStaff()) {
            return $p->lhu?->nomor !== null;
        }

        return $p->status === Status::Selesai;
    }
}
