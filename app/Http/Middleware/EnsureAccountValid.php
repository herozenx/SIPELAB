<?php

namespace App\Http\Middleware;

use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setiap request terautentikasi dicek ulang ke database:
 * - akun dinonaktifkan / versi sesi berubah (ganti sandi, ubah peran) => sesi langsung dicabut
 * - batas umur sesi absolut (tidak bisa diperpanjang terus walau aktif)
 * - akun dengan sandi sementara wajib mengganti sandi dulu
 */
class EnsureAccountValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $session = $request->session();
        $maxAge = (int) config('sipelab.session_absolute_minutes', 600) * 60;
        $expired = (time() - (int) $session->get('login_at', 0)) > $maxAge;

        if (! $user->aktif || (int) $session->get('auth_version', -1) !== (int) $user->auth_version || $expired) {
            Audit::log($expired ? 'SESI_KEDALUWARSA' : 'SESI_DICABUT', 'user', $user->id, [], $user->id);
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->route('login')->with('warning', $expired
                ? 'Sesi Anda sudah berakhir. Silakan masuk kembali.'
                : 'Sesi Anda tidak berlaku lagi. Silakan masuk kembali.');
        }

        if ($user->wajib_ganti_password && ! $request->routeIs('akun', 'akun.password', 'logout')) {
            return redirect()->route('akun')->with('warning', 'Demi keamanan, ganti kata sandi sementara Anda sebelum melanjutkan.');
        }

        return $next($request);
    }
}
