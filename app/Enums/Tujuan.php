<?php

namespace App\Enums;

enum Tujuan: string
{
    case Domestik = 'DOMESTIK';
    case Ekspor = 'EKSPOR';

    public function label(): string
    {
        return ucfirst(strtolower($this->value));
    }
}
