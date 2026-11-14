<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisBayar;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $role = Role::tryFrom((string) $request->query('role'));
        $cari = trim((string) $request->query('cari'));
        $q = User::query()->with('lab')->orderBy('role')->orderBy('nama');
        if ($role) {
            $q->where('role', $role->value);
        }
        if ($cari !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_substr($cari, 0, 60)).'%';
            $q->where(fn ($w) => $w->where('nama', 'like', $like)->orWhere('email', 'like', $like)->orWhere('instansi', 'like', $like));
        }

        return view('admin.users', [
            'rows' => $q->paginate(20)->withQueryString(),
            'labs' => Lab::query()->orderBy('urutan')->get(),
            'role' => $role,
            'cari' => $cari,
            'edit' => $request->query('edit') ? User::query()->find((int) $request->query('edit')) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $sandi = $data['password'] ?? null;
        $user = User::query()->create($data + ['wajib_ganti_password' => true]);
        Audit::log('USER_TAMBAH', 'user', $user->id, ['email' => $user->email, 'role' => $user->role->value]);

        return redirect()->route('admin.users')->with('success', "Akun {$user->email} dibuat. Sampaikan kata sandi sementara secara langsung; pemilik akun wajib menggantinya saat pertama masuk.");
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        $me = $request->user();
        if ($user->is($me) && ($data['role'] !== Role::Superadmin->value || ! $data['aktif'])) {
            return back()->with('error', 'Anda tidak bisa menurunkan peran atau menonaktifkan akun Anda sendiri.');
        }

        $sensitif = $user->role->value !== $data['role'] || $user->aktif !== (bool) $data['aktif'] || $user->lab_id !== ($data['lab_id'] ?? null);
        $user->fill($data)->save();
        if ($sensitif) {
            $user->revokeSessions(); // perubahan hak akses berlaku seketika
        }
        Audit::log('USER_UBAH', 'user', $user->id, ['role' => $user->role->value, 'aktif' => $user->aktif]);

        return redirect()->route('admin.users')->with('success', "Akun {$user->email} diperbarui.");
    }

    public function resetPassword(Request $request, User $user)
    {
        $sementara = Str::password(12, symbols: false);
        $user->forceFill(['password' => $sementara, 'wajib_ganti_password' => true, 'failed_attempts' => 0, 'locked_until' => null])->save();
        $user->revokeSessions();
        Audit::log('USER_RESET_PASSWORD', 'user', $user->id);

        return redirect()->route('admin.users')
            ->with('success', "Kata sandi {$user->email} direset. Semua sesinya sudah dikeluarkan.")
            ->with('sandi_sementara', $sementara);
    }

    public function unlock(User $user)
    {
        $user->forceFill(['failed_attempts' => 0, 'locked_until' => null])->save();
        Audit::log('USER_BUKA_KUNCI', 'user', $user->id);

        return back()->with('success', "Akun {$user->email} dibuka kembali.");
    }

    private function validated(Request $request, ?User $user): array
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email'))), 'aktif' => $request->boolean('aktif')]);
        $data = $request->validate([
            'nama' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:120', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::enum(Role::class)],
            'lab_id' => ['nullable', 'required_if:role,petugas_lab', 'exists:labs,id'],
            'instansi' => ['nullable', 'string', 'max:150'],
            'telepon' => ['nullable', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'aktif' => ['boolean'],
            'password' => [$user ? 'nullable' : 'required', Password::defaults()],
        ], ['lab_id.required_if' => 'Petugas lab harus ditempatkan di salah satu lab.']);

        if ($data['role'] !== Role::PetugasLab->value) {
            $data['lab_id'] = null;
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
