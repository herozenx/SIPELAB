<?php

namespace App\Enums;

enum Tujuan: string
{
    case Domestik = 'DOMESTIK';
    case Ekspor = 'EKSPOR';
    case Impor = 'IMPOR';

    public function label(): string
    {
        return ucfirst(strtolower($this->value));
    }
}
