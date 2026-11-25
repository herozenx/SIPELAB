<?php

namespace App\Http\Controllers\Auth;

use App\Enums\JenisBayar;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $email = mb_strtolower(trim((string) $googleUser->getEmail()));

            $user = User::query()->where('email', $email)->first();

            if ($user) {
                if ($user->locked_until && $user->locked_until->isFuture()) {
                    $menit = (int) ceil(now()->diffInSeconds($user->locked_until) / 60);
                    Audit::log('LOGIN_TERKUNCI', 'user', $user->id, [], $user->id);

                    return redirect()->route('login')->with('error', "Akun dikunci sementara. Coba lagi dalam {$menit} menit.");
                }

                if (! $user->aktif) {
                    Audit::log('LOGIN_GAGAL', 'user', $user->id, [], $user->id);

                    return redirect()->route('login')->with('error', 'Akun Anda tidak aktif. Hubungi administrator.');
                }

                $user->forceFill([
                    'google_id' => $googleUser->getId(),
                    'failed_attempts' => 0,
                    'locked_until' => null,
                    'last_login_at' => now(),
                    'last_login_ip' => $request->ip(),
                ])->save();

                Audit::log('LOGIN', 'user', $user->id);
            } else {
                $user = User::query()->create([
                    'nama' => $googleUser->getName(),
                    'email' => $email,
                    'google_id' => $googleUser->getId(),
                    'role' => Role::PenggunaJasa,
                    'tipe_pengguna' => JenisBayar::Mandiri,
                ]);

                $user->forceFill([
                    'last_login_at' => now(),
                    'last_login_ip' => $request->ip(),
                ])->save();

                Audit::log('DAFTAR', 'user', $user->id, [], $user->id);
            }

            Auth::login($user);
            $request->session()->regenerate(true);
            $request->session()->put([
                'auth_version' => (int) ($user->auth_version ?? 0),
                'login_at' => time(),
            ]);

            return redirect()->intended(route('dashboard'));
        } catch (Exception $e) {
            return redirect()->route('login')->with('error', 'Autentikasi Google gagal: ' . $e->getMessage());
        }
    }
}
