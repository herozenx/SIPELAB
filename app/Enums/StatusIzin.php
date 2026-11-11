<?php

namespace App\Enums;

enum StatusIzin: string
{
    case Lulus = 'LULUS';
    case TidakLulus = 'TIDAK_LULUS';

    public function label(): string
    {
        return $this === self::Lulus ? 'Lulus' : 'Tidak lulus';
    }

    /** Kalimat keputusan pada LHU, menyesuaikan tujuan distribusi. */
    public function keputusan(?Tujuan $tujuan = null): string
    {
        $jalur = match ($tujuan) {
            Tujuan::Ekspor => 'ekspor',
            Tujuan::Impor => 'impor',
            default => 'beredar',
        };

        return $this === self::Lulus
            ? 'Lulus uji, bisa '.$jalur
            : 'Tidak lulus uji, tidak bisa '.$jalur;
    }
}
