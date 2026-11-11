<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencatat waktu aktivitas terakhir (untuk status "online" di chat & tim operator).
 * Ditulis paling sering sekali per menit per akun agar tidak membebani database.
 */
class CatatAktivitas
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $u = $request->user();
        if ($u) {
            // Dibaca mentah: akun yang baru dibuat di memori belum memuat kolom ini (mode ketat Eloquent).
            $terakhir = $u->getAttributes()['last_seen_at'] ?? null;
            if (! $terakhir || Carbon::parse($terakhir)->lt(now()->subMinute())) {
                DB::table('users')->where('id', $u->id)->update(['last_seen_at' => now()]);
            }
        }

        return $response;
    }
}
