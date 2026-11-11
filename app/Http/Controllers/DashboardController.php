<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\Status;
use App\Models\HasilUji;
use App\Models\Lab;
use App\Models\Lhu;
use App\Models\Permohonan;
use App\Models\TahapLab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();

        return match ($u->role) {
            Role::Superadmin, Role::AdminLab => $this->admin(),
            Role::PetugasLab => $this->petugas($request),
            Role::PenggunaJasa => $this->pengguna($request),
        };
    }

    private function admin()
    {
        $tindakan = Permohonan::query()->with(['pemohon', 'labSaatIni', 'lhu'])
            ->whereIn('status', [Status::Diajukan->value, Status::MenungguKonfirmasiBayar->value, Status::MenungguValidasi->value])
            ->oldest('updated_at')->get()->groupBy(fn ($p) => $p->status->value);

        $diLab = Permohonan::query()->where('status', Status::DiLab->value)
            ->select('current_lab_id', DB::raw('COUNT(*) as n'))->groupBy('current_lab_id')->pluck('n', 'current_lab_id');

        // Sampel yang sedang di lab dan sudah melewati batas norma waktu lab tersebut.
        $lewatNorma = TahapLab::query()->whereNull('selesai_at')->where('batas_at', '<', now())
            ->whereHas('permohonan', fn ($q) => $q->where('status', Status::DiLab->value))
            ->with(['lab', 'operator', 'permohonan'])->oldest('batas_at')->get();

        return view('dashboard.admin', [
            'lewatNorma' => $lewatNorma,
            'verifikasi' => $tindakan->get(Status::Diajukan->value, collect()),
            'bayar' => $tindakan->get(Status::MenungguKonfirmasiBayar->value, collect()),
            'validasi' => $tindakan->get(Status::MenungguValidasi->value, collect()),
            'labs' => Lab::query()->aktif()->get(),
            'diLab' => $diLab,
            'menungguBayar' => Permohonan::query()->where('status', Status::MenungguPembayaran->value)->count(),
        ]);
    }

    private function petugas(Request $request)
    {
        $u = $request->user()->load('lab');
        $antrean = Permohonan::query()
            ->where('status', Status::DiLab->value)->where('current_lab_id', $u->lab_id)
            ->with(['tahapAktif.operator'])
            ->get()
            // Paling mendesak (batas norma terdekat) di atas.
            ->sortBy(fn ($p) => $p->tahapAktif?->batas_at?->timestamp ?? PHP_INT_MAX)->values();
        $hariIni = HasilUji::query()->where('lab_id', $u->lab_id)->where('petugas_id', $u->id)
            ->where('created_at', '>=', now()->startOfDay())->count();

        return view('dashboard.petugas', ['u' => $u, 'antrean' => $antrean, 'hariIni' => $hariIni]);
    }

    private function pengguna(Request $request)
    {
        $u = $request->user();
        $rows = Permohonan::query()->with(['labSaatIni', 'lhu'])->where('user_id', $u->id)->latest('updated_at')->limit(8)->get();
        $perluTindakan = $rows->where('status', Status::MenungguPembayaran);

        return view('dashboard.pengguna', ['u' => $u, 'rows' => $rows, 'perluTindakan' => $perluTindakan, 'labs' => Lab::query()->aktif()->get()]);
    }

    public function riwayat(Request $request)
    {
        $u = $request->user();
        $rows = HasilUji::query()->with('permohonan')->where('lab_id', $u->lab_id)
            ->latest()->paginate(20);

        return view('dashboard.riwayat', ['rows' => $rows]);
    }

    public function laporan()
    {
        $st = Permohonan::query()->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->pluck('n', 'status');
        $iz = Lhu::query()->whereNotNull('nomor')->select('status_izin', DB::raw('COUNT(*) as n'))->groupBy('status_izin')->pluck('n', 'status_izin');
        $pnbp = (int) Permohonan::query()->whereIn('status', [Status::DiLab->value, Status::MenungguValidasi->value, Status::Selesai->value])->sum('tagihan');

        $durasi = Lhu::query()->whereNotNull('validated_at')->with('permohonan:id,created_at')->get(['permohonan_id', 'validated_at'])
            ->map(fn ($l) => $l->permohonan->created_at->diffInMinutes($l->validated_at) / 1440);
        $rata = $durasi->isEmpty() ? null : round($durasi->avg(), 1);

        $perLab = Lab::query()->orderBy('urutan')->get()->map(fn ($l) => [
            'nama' => $l->nama,
            'negatif' => HasilUji::query()->where('lab_id', $l->id)->where('hasil', 'NEGATIF')->count(),
            'positif' => HasilUji::query()->where('lab_id', $l->id)->where('hasil', 'POSITIF')->count(),
        ]);

        $bulan = collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $mulai = $bulan->first();
        $hitung = Permohonan::query()->where('created_at', '>=', $mulai)->get(['created_at'])
            ->countBy(fn ($p) => $p->created_at->format('Y-m'));
        $bulanan = $bulan->map(fn ($b) => ['label' => $b, 'n' => (int) ($hitung[$b->format('Y-m')] ?? 0)]);

        return view('dashboard.laporan', [
            'st' => $st, 'iz' => $iz, 'pnbp' => $pnbp, 'rata' => $rata,
            'perLab' => $perLab, 'bulanan' => $bulanan, 'total' => (int) $st->sum(),
        ]);
    }
}
