<?php

namespace App\Http\Controllers;

use App\Enums\JenisBayar;
use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:120'],
            'password' => ['required', 'string', 'max:128'],
            'portal' => ['required', Rule::in(['pengguna_jasa', 'operator', 'superadmin'])],
        ], [], ['portal' => 'masuk sebagai']);

        $email = mb_strtolower(trim($data['email']));
        $user = User::query()->where('email', $email)->first();
        $fail = fn () => throw ValidationException::withMessages(['email' => 'Email, kata sandi, atau pilihan "masuk sebagai" tidak cocok.']);

        if (! $user) {
            // Hash tiruan agar waktu respons sama dengan akun yang ada (cegah user enumeration).
            Hash::check($data['password'], '$2y$12$05RM7BDVSKeoS7vCJW/CCuYqcUUCEn6ln.AvhIkmZuLgXFqQdQdge');
            Audit::log('LOGIN_GAGAL', 'user', null, ['email' => $email]);
            $fail();
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            $menit = (int) ceil(now()->diffInSeconds($user->locked_until) / 60);
            Audit::log('LOGIN_TERKUNCI', 'user', $user->id, [], $user->id);
            throw ValidationException::withMessages(['email' => "Akun dikunci sementara karena terlalu banyak percobaan gagal. Coba lagi dalam {$menit} menit."]);
        }

        if (! Hash::check($data['password'], $user->password) || $user->role->portal() !== $data['portal'] || ! $user->aktif) {
            $n = $user->failed_attempts + 1;
            $lock = $n >= config('sipelab.lockout_attempts');
            $user->forceFill($lock
                ? ['failed_attempts' => 0, 'locked_until' => now()->addMinutes(config('sipelab.lockout_minutes'))]
                : ['failed_attempts' => $n])->save();
            Audit::log($lock ? 'AKUN_DIKUNCI' : 'LOGIN_GAGAL', 'user', $user->id, [], $user->id);
            $fail();
        }

        if (Hash::needsRehash($user->password)) {
            $user->password = $data['password'];
        }
        $user->forceFill([
            'failed_attempts' => 0, 'locked_until' => null,
            'last_login_at' => now(), 'last_login_ip' => $request->ip(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate(true); // cegah session fixation
        $request->session()->put(['auth_version' => $user->auth_version, 'login_at' => time()]);
        Audit::log('LOGIN', 'user', $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Audit::log('LOGOUT', 'user', $request->user()?->id);
        // Langsung tampil offline di chat & tim operator.
        $request->user()?->forceFill(['last_seen_at' => null])->saveQuietly();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda sudah keluar.');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'nama' => ['required', 'string', 'min:3', 'max:100'],
            'instansi' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:120', 'unique:users,email'],
            'telepon' => ['required', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'telepon.regex' => 'Nomor telepon 8–20 digit, boleh memakai + atau -.',
        ]);

        $user = User::query()->create($data + ['role' => Role::PenggunaJasa]);
        Audit::log('DAFTAR', 'user', $user->id, [], $user->id);

        Auth::login($user);
        $request->session()->regenerate(true);
        $request->session()->put(['auth_version' => $user->auth_version, 'login_at' => time()]);

        return redirect()->route('dashboard')->with('success', 'Akun pengguna jasa berhasil dibuat. Silakan ajukan permohonan uji pertama Anda.');
    }

    public function akun(Request $request)
    {
        return view('auth.akun', ['user' => $request->user()->load('lab')]);
    }

    public function gantiPassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ], [
            'current_password.current_password' => 'Kata sandi saat ini salah.',
            'password.different' => 'Kata sandi baru harus berbeda dari yang lama.',
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $request->input('password'), 'wajib_ganti_password' => false])->save();
        $user->revokeSessions(); // keluarkan perangkat lain
        $request->session()->regenerate(true);
        $request->session()->put('auth_version', $user->fresh()->auth_version);
        Audit::log('GANTI_PASSWORD', 'user', $user->id);

        return redirect()->route('dashboard')->with('success', 'Kata sandi diganti. Sesi di perangkat lain sudah dikeluarkan.');
    }
}
