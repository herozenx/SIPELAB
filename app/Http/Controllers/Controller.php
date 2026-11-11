<?php

namespace App\Http\Controllers;

use App\Models\Permohonan;
use Illuminate\Support\Facades\Gate;

abstract class Controller
{
    /**
     * Data milik orang lain dijawab 404, bukan 403,
     * supaya keberadaan nomor permohonan tidak bisa ditebak (anti-IDOR/enumerasi).
     */
    protected function authorizeOr404(string $ability, Permohonan $p): void
    {
        if (Gate::denies($ability, $p)) {
            abort(404);
        }
    }
}
