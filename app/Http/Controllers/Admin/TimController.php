<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Models\TahapLab;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin Lab (dan Superadmin) mengelola operator tiap tim lab sendiri:
 * tambah operator, pindah tim, nonaktifkan, reset sandi, buka kunci.
 * Hanya akun berperan Petugas Lab yang bisa diubah dari sini.
 */
class TimController extends Controller
{
    public function index(Request $request)
    {
        $labs = Lab::query()->orderBy('urutan')->with(['operator' => fn ($q) => $q->orderByDesc('aktif')->orderBy('nama')])->get();
        $tanpaLab = User::query()->where('role', Role::PetugasLab->value)->whereNull('lab_id')->orderBy('nama')->get();

        // Beban kerja: jumlah sampel yang sedang ditangani tiap operator.
        $beban = TahapLab::query()->whereNull('selesai_at')
            ->join('tahap_operator', 'tahap_operator.tahap_lab_id', '=', 'tahap_lab.id')
            ->selectRaw('tahap_operator.user_id, COUNT(*) as n')->groupBy('tahap_operator.user_id')->pluck('n', 'user_id');

        $edit = null;
        if ($request->query('edit')) {
            $edit = User::query()->where('role', Role::PetugasLab->value)->find((int) $request->query('edit'));
        }

        return view('admin.tim', compact('labs', 'tanpaLab', 'beban', 'edit'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $sementara = Str::password(12, symbols: false);
        $user = User::query()->create($data + [
            'role' => Role::PetugasLab, 'password' => $sementara, 'wajib_ganti_password' => true, 'aktif' => true,
        ]);
        Audit::log('OPERATOR_TAMBAH', 'user', $user->id, ['email' => $user->email, 'lab_id' => $user->lab_id]);

        return redirect()->route('tim.index')
            ->with('success', "Operator {$user->nama} ditambahkan ke tim Lab {$user->lab->nama}.")
            ->with('sandi_sementara', $sementara)->with('sandi_untuk', $user->email);
    }

    public function update(Request $request, User $user)
    {
        $this->pastikanOperator($user);
        $request->merge(['aktif' => $request->boolean('aktif')]);
        $data = $this->validated($request, $user) + $request->validate(['aktif' => ['boolean']]);

        $sensitif = $user->lab_id !== (int) $data['lab_id'] || $user->aktif !== (bool) $data['aktif'];
        $user->fill($data)->save();
        if ($sensitif) {
            $user->revokeSessions(); // pindah tim / nonaktif berlaku seketika
        }
        Audit::log('OPERATOR_UBAH', 'user', $user->id, ['lab_id' => $user->lab_id, 'aktif' => $user->aktif]);

        return redirect()->route('tim.index')->with('success', "Data operator {$user->nama} disimpan.");
    }

    public function resetPassword(User $user)
    {
        $this->pastikanOperator($user);
        $sementara = Str::password(12, symbols: false);
        $user->forceFill(['password' => $sementara, 'wajib_ganti_password' => true, 'failed_attempts' => 0, 'locked_until' => null])->save();
        $user->revokeSessions();
        Audit::log('OPERATOR_RESET_PASSWORD', 'user', $user->id);

        return redirect()->route('tim.index')
            ->with('success', "Kata sandi {$user->nama} direset.")
            ->with('sandi_sementara', $sementara)->with('sandi_untuk', $user->email);
    }

    public function unlock(User $user)
    {
        $this->pastikanOperator($user);
        $user->forceFill(['failed_attempts' => 0, 'locked_until' => null])->save();
        Audit::log('OPERATOR_BUKA_KUNCI', 'user', $user->id);

        return back()->with('success', "Akun {$user->nama} dibuka kembali.");
    }

    /** Admin Lab tidak boleh mengubah akun admin/superadmin/pengguna jasa lewat menu ini. */
    private function pastikanOperator(User $user): void
    {
        abort_unless($user->role === Role::PetugasLab, 404);
    }

    private function validated(Request $request, ?User $user): array
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        return $request->validate([
            'nama' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:120', Rule::unique('users', 'email')->ignore($user?->id)],
            'lab_id' => ['required', 'exists:labs,id'],
            'telepon' => ['nullable', 'regex:/^[0-9+\-\s]{8,20}$/'],
        ], ['lab_id.required' => 'Pilih tim lab untuk operator ini.']);
    }
}
