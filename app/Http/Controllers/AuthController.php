<?php

namespace App\Http\Controllers;

use App\Enums\JenisBayar;
use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        ]);

        $email = mb_strtolower(trim($data['email']));
        $user = User::query()->where('email', $email)->first();
        $fail = fn () => throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak cocok.']);

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

        // Operator perusahaan ikut nonaktif bila akun admin perusahaannya dinonaktifkan.
        $indukAktif = $user->induk_id === null || (bool) User::query()->whereKey($user->induk_id)->value('aktif');
        if (! Hash::check($data['password'], $user->password) || ! $user->aktif || ! $indukAktif) {
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
        $telepon = preg_replace('/[^0-9]/', '', (string) $request->input('telepon_lokal', $request->input('telepon')));
        if (str_starts_with($telepon, '0')) {
            $telepon = substr($telepon, 1);
        }
        $request->merge(['telepon' => str_starts_with($telepon, '62') ? '+'.$telepon : '+62'.$telepon]);
        $operator = $this->rapikanOperator($request->input('operator', []));
        $request->merge(['operator' => $operator]);
        $data = $request->validate([
            'nama' => ['required', 'string', 'min:3', 'max:100'],
            'instansi' => ['required', 'string', 'min:2', 'max:150'],
            'alamat' => ['required', 'string', 'min:5', 'max:300'],
            'email' => ['required', 'email:rfc', 'max:120', 'unique:users,email'],
            'telepon' => ['required', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ] + $this->aturanOperator('operator.*.'), [
            'telepon.regex' => 'Nomor telepon 8–20 digit, boleh memakai + atau -.',
            'alamat.required' => 'Isi alamat usaha/instansi; alamat ini dicetak di LHP.',
        ] + $this->pesanOperator('operator.*.'), $this->namaOperator('operator.*.'));
        $emailOp = collect($operator)->pluck('email');
        if ($emailOp->contains($data['email']) || $emailOp->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['operator' => 'Email setiap operator harus berbeda dan tidak sama dengan email admin perusahaan.']);
        }

        $user = DB::transaction(function () use ($data, $operator) {
            $user = User::query()->create(collect($data)->except('operator')->all() + ['role' => Role::PenggunaJasa]);
            foreach ($operator as $op) {
                $this->buatOperator($user, $op);
            }

            return $user;
        });
        Audit::log('DAFTAR', 'user', $user->id, ['operator' => count($operator)], $user->id);

        Auth::login($user);
        $request->session()->regenerate(true);
        $request->session()->put(['auth_version' => $user->auth_version, 'login_at' => time()]);

        return redirect()->route('dashboard')->with('success', 'Akun pengguna jasa berhasil dibuat. Silakan ajukan permohonan uji pertama Anda.');
    }

    public function akun(Request $request)
    {
        $user = $request->user()->load(['lab', 'induk']);

        return view('auth.akun', [
            'user' => $user,
            'operator' => $user->adminPerusahaan() ? $user->operatorPerusahaan()->get() : collect(),
            'maksOperator' => self::MAKS_OPERATOR,
        ]);
    }

    /** Admin perusahaan menambah akun operator (personel yang dihubungi). */
    public function tambahOperator(Request $request)
    {
        $admin = $request->user();
        abort_unless($admin->adminPerusahaan(), 403);
        if ($admin->operatorPerusahaan()->count() >= self::MAKS_OPERATOR) {
            return back()->with('error', 'Satu perusahaan paling banyak '.self::MAKS_OPERATOR.' operator.');
        }
        $op = $this->rapikanOperator([$request->input('op', [])])[0] ?? [];
        $request->merge(['op' => $op]);
        $data = $request->validate($this->aturanOperator('op.'), $this->pesanOperator('op.'), $this->namaOperator('op.'));
        $baru = $this->buatOperator($admin, $data['op']);
        Audit::log('TAMBAH_OPERATOR', 'user', $baru->id);

        return back()->with('success', "Operator {$baru->nama} ditambahkan. Ia bisa masuk dengan email {$baru->email}.");
    }

    /** Admin perusahaan mengaktifkan/menonaktifkan operatornya. */
    public function aturOperator(Request $request, User $operator)
    {
        $admin = $request->user();
        abort_unless($admin->adminPerusahaan() && $operator->induk_id === $admin->id, 404);
        $operator->forceFill(['aktif' => ! $operator->aktif])->save();
        if (! $operator->aktif) {
            $operator->revokeSessions();
        }
        Audit::log($operator->aktif ? 'AKTIFKAN_OPERATOR' : 'NONAKTIFKAN_OPERATOR', 'user', $operator->id);

        return back()->with('success', "Operator {$operator->nama} ".($operator->aktif ? 'diaktifkan kembali.' : 'dinonaktifkan dan dikeluarkan dari semua perangkat.'));
    }

    public const MAKS_OPERATOR = 10;

    /** Buang blok operator yang kosong; rapikan email & telepon. */
    private function rapikanOperator(mixed $daftar): array
    {
        return collect(is_array($daftar) ? $daftar : [])
            ->filter(fn ($x) => is_array($x) && collect($x)->only(['nama', 'nik', 'email', 'telepon', 'password'])->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty())
            ->map(function ($x) {
                $tel = preg_replace('/[^0-9]/', '', (string) ($x['telepon'] ?? ''));
                $tel = str_starts_with($tel, '0') ? substr($tel, 1) : $tel;

                return [
                    'nama' => trim((string) ($x['nama'] ?? '')),
                    'nik' => preg_replace('/\D/', '', (string) ($x['nik'] ?? '')),
                    'email' => mb_strtolower(trim((string) ($x['email'] ?? ''))),
                    'telepon' => $tel === '' ? null : (str_starts_with($tel, '62') ? '+'.$tel : '+62'.$tel),
                    'password' => (string) ($x['password'] ?? ''),
                ];
            })->take(self::MAKS_OPERATOR)->values()->all();
    }

    private function aturanOperator(string $a): array
    {
        return [
            rtrim($a, '.*') => ['nullable', 'array', 'max:'.self::MAKS_OPERATOR],
            $a.'nama' => ['required', 'string', 'min:3', 'max:100'],
            $a.'nik' => ['required', 'digits:16'],
            $a.'email' => ['required', 'email:rfc', 'max:120', 'unique:users,email'],
            $a.'telepon' => ['nullable', 'regex:/^[0-9+\-\s]{8,20}$/'],
            $a.'password' => ['required', Password::defaults()],
        ];
    }

    private function pesanOperator(string $a): array
    {
        return [
            $a.'nik.digits' => 'NIK terdiri dari 16 angka.',
            $a.'email.unique' => 'Email operator ini sudah terdaftar.',
            $a.'telepon.regex' => 'Nomor HP operator 8–20 digit.',
        ];
    }

    private function namaOperator(string $a): array
    {
        return [$a.'nama' => 'nama operator', $a.'nik' => 'NIK operator', $a.'email' => 'email operator', $a.'telepon' => 'No. HP operator', $a.'password' => 'kata sandi operator'];
    }

    private function buatOperator(User $admin, array $op): User
    {
        return User::query()->create([
            'nama' => $op['nama'], 'nik' => $op['nik'], 'email' => $op['email'], 'telepon' => $op['telepon'] ?? null,
            'password' => $op['password'], 'role' => Role::PenggunaJasa, 'induk_id' => $admin->id,
            'instansi' => $admin->instansi, 'alamat' => $admin->alamat,
        ]);
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
