<?php

namespace App\Http\Controllers;

use App\Enums\JenisBayar;
use App\Enums\Role;
use App\Enums\Status;
use App\Models\Lab;
use App\Models\Lhu;
use App\Models\Pengembalian;
use App\Models\Permohonan;
use App\Models\PermohonanParameter;
use App\Models\Persetujuan;
use App\Models\TahapLab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Antrean tugas per status: [status, judul, keterangan, peran yang menindaklanjuti].
     * Administrator melihat semua antrean (memantau dari awal sampai akhir).
     */
    private const ANTREAN = [
        [Status::Diajukan, 'Verifikasi permohonan', 'Periksa berkas dan data permohonan: diterima atau ditolak dengan alasan.', [Role::Superadmin]],
        [Status::Diterima, 'Registrasi sampel', 'Terima sampel fisik, kaji ulang permintaan, tetapkan jenis layanan, catat ke buku sampel.', [Role::Operator, Role::AdminLab]],
        [Status::MenungguBilling, 'Terbitkan billing SIMPONI', 'Mandiri sebelum diuji; layanan lain setelah LHP disahkan.', [Role::Bendahara]],
        [Status::MenungguKonfirmasiBayar, 'Cek pembayaran', 'Cocokkan bukti setor dengan dana masuk di SIMPONI.', [Role::Bendahara]],
        [Status::SiapUji, 'Terbitkan surat tugas', 'Surat tugas pengujian ke penyelia setiap bidang yang terlibat.', [Role::AdminLab]],
        [Status::MenungguPemeriksaan, 'Validasi hasil pengujian', 'Periksa kesesuaian hasil dengan permohonan dan pastikan semua parameter terisi.', [Role::Superadmin]],
        [Status::MenungguParaf, 'Menunggu paraf penyelia', 'Penyelia tiap bidang memaraf hasil bidangnya.', []],
        [Status::MenungguPengesahan, 'Validasi akhir LHP', 'Validasi akhir dan pengesahan LHP bernomor.', [Role::PenanggungJawab]],
    ];

    public function index(Request $request)
    {
        $u = $request->user();

        return match (true) {
            $u->role === Role::PenggunaJasa => $this->pengguna($request),
            $u->isLab() => $this->lab($request),
            default => $this->staff($request),
        };
    }

    private function staff(Request $request)
    {
        $u = $request->user();
        $semua = $u->role === Role::Superadmin;
        $antrean = collect(self::ANTREAN)
            ->filter(fn ($a) => $semua || in_array($u->role, $a[3], true))
            ->map(fn ($a) => [
                'status' => $a[0], 'judul' => $a[1], 'ket' => $a[2],
                'rows' => Permohonan::query()->with(['pemohon', 'lhu'])->where('status', $a[0]->value)
                    ->when($a[0] === Status::MenungguBilling, fn ($q) => $q->with('lhu'))
                    ->oldest('updated_at')->limit(30)->get(),
            ])->values();

        // LHP selesai yang perlu dicetak/diserahkan di kantor (Operator & petugas yang boleh mencetak).
        $lhpSelesai = $u->role->bolehCetakLhp()
            ? Permohonan::query()->with(['pemohon', 'lhu'])->where('status', Status::Selesai->value)->latest('updated_at')->limit(8)->get()
            : collect();

        $papan = in_array($u->role, [Role::Superadmin, Role::AdminLab, Role::PenanggungJawab], true);
        $hitung = Permohonan::query()->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->pluck('n', 'status');

        // Sampel yang sedang di lab dan sudah melewati batas norma waktu lab tersebut.
        $lewatNorma = $papan ? TahapLab::query()->whereNull('selesai_at')->where('batas_at', '<', now())
            ->whereHas('permohonan', fn ($q) => $q->where('status', Status::DiLab->value))
            ->with(['lab', 'operator', 'permohonan'])->oldest('batas_at')->get() : collect();

        $dikembalikan = $papan ? Pengembalian::query()->whereNull('selesai_at')->with(['lab', 'pengirim', 'permohonan'])->latest('id')->get() : collect();

        return view('dashboard.staff', compact('antrean', 'lhpSelesai', 'papan', 'hitung', 'lewatNorma', 'dikembalikan', 'semua'));
    }

    /** Analis & penyelia: antrean lab sendiri; penyelia juga melihat LHP yang menunggu parafnya. */
    private function lab(Request $request)
    {
        $u = $request->user()->load('lab');
        $antrean = Permohonan::query()->where('status', Status::DiLab->value)
            ->whereHas('tahap', fn ($t) => $t->where('lab_id', $u->lab_id)->whereNull('selesai_at'))
            ->with([
                'tahap' => fn ($t) => $t->where('lab_id', $u->lab_id)->with('operator'),
                'parameter' => fn ($q) => $q->where('lab_id', $u->lab_id),
                'pengembalian' => fn ($q) => $q->where('lab_id', $u->lab_id)->whereNull('selesai_at'),
                'suratTugas' => fn ($q) => $q->where('lab_id', $u->lab_id)->with('penyelia'),
            ])
            ->get()
            ->sortBy(fn ($p) => $p->tahap->first()?->batas_at?->timestamp ?? PHP_INT_MAX)->values();

        $paraf = collect();
        if ($u->role === Role::Penyelia) {
            $sudah = Persetujuan::query()->where('jenis', Persetujuan::PARAF)->where('lab_id', $u->lab_id)->pluck('permohonan_id');
            $paraf = Permohonan::query()->where('status', Status::MenungguParaf->value)
                ->whereHas('parameter', fn ($q) => $q->where('lab_id', $u->lab_id))
                ->whereNotIn('id', $sudah)->oldest('updated_at')->get();
        }
        $hariIni = PermohonanParameter::query()->where('lab_id', $u->lab_id)->where('diinput_oleh', $u->id)
            ->where('diinput_at', '>=', now()->startOfDay())->count();

        return view('dashboard.lab', ['u' => $u, 'antrean' => $antrean, 'paraf' => $paraf, 'hariIni' => $hariIni]);
    }

    private function pengguna(Request $request)
    {
        $u = $request->user();
        $baseQuery = Permohonan::query()->where('user_id', $u->id);

        $counts = [
            'total' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->whereIn('status', [
                Status::Diajukan->value,
                Status::Diterima->value,
                Status::MenungguBilling->value,
                Status::MenungguPembayaran->value,
                Status::MenungguKonfirmasiBayar->value,
            ])->count(),
            'diproses' => (clone $baseQuery)->whereIn('status', [
                Status::SiapUji->value,
                Status::DiLab->value,
                Status::MenungguPemeriksaan->value,
                Status::MenungguParaf->value,
                Status::MenungguPengesahan->value,
            ])->count(),
            'selesai' => (clone $baseQuery)->where('status', Status::Selesai->value)->count(),
        ];

        $rows = (clone $baseQuery)->with(['lhu'])->latest('updated_at')->limit(5)->get();
        $perluTindakan = (clone $baseQuery)->with(['lhu'])->where('status', Status::MenungguPembayaran->value)->get();

        return view('dashboard.pengguna', [
            'u' => $u,
            'rows' => $rows,
            'counts' => $counts,
            'perluTindakan' => $perluTindakan,
            'labs' => Lab::query()->aktif()->get(),
        ]);
    }

    /** Riwayat pengujian lab petugas (analis & penyelia). */
    public function riwayat(Request $request)
    {
        $u = $request->user();
        $rows = TahapLab::query()->with(['permohonan'])->where('lab_id', $u->lab_id)->whereNotNull('selesai_at')
            ->latest('selesai_at')->paginate(20);
        $jumlahParam = PermohonanParameter::query()->where('lab_id', $u->lab_id)
            ->whereIn('permohonan_id', $rows->pluck('permohonan_id'))
            ->select('permohonan_id', DB::raw('COUNT(*) as n'), DB::raw('SUM(CASE WHEN memenuhi = false THEN 1 ELSE 0 END) as tidak'))
            ->groupBy('permohonan_id')->get()->keyBy('permohonan_id');

        return view('dashboard.riwayat', ['rows' => $rows, 'jumlahParam' => $jumlahParam]);
    }

    public function laporan()
    {
        $st = Permohonan::query()->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->pluck('n', 'status');
        $iz = Lhu::query()->whereNotNull('nomor')->select('status_izin', DB::raw('COUNT(*) as n'))->groupBy('status_izin')->pluck('n', 'status_izin');

        // PNBP terkonfirmasi: Mandiri setelah lunas (sudah lewat tahap bayar), lainnya setelah pelunasan (selesai).
        $lunasMandiri = [Status::SiapUji, Status::DiLab, Status::MenungguPemeriksaan, Status::MenungguParaf, Status::MenungguPengesahan, Status::Selesai];
        $pnbp = (int) Permohonan::query()->where(fn ($q) => $q
            ->where(fn ($m) => $m->where('jenis_bayar', JenisBayar::Mandiri->value)->whereIn('status', array_map(fn ($s) => $s->value, $lunasMandiri)))
            ->orWhere(fn ($m) => $m->where('jenis_bayar', '!=', JenisBayar::Mandiri->value)->where('status', Status::Selesai->value))
        )->sum('tagihan');

        $durasi = Lhu::query()->whereNotNull('validated_at')->with('permohonan:id,created_at')->get(['permohonan_id', 'validated_at'])
            ->map(fn ($l) => $l->permohonan->created_at->diffInMinutes($l->validated_at) / 1440);
        $rata = $durasi->isEmpty() ? null : round($durasi->avg(), 1);

        $per = PermohonanParameter::query()->whereNotNull('memenuhi')
            ->select('lab_id', 'memenuhi', DB::raw('COUNT(*) as n'))->groupBy('lab_id', 'memenuhi')->get();
        $perLab = Lab::query()->orderBy('urutan')->get()->map(fn ($l) => [
            'nama' => $l->nama,
            'negatif' => (int) $per->where('lab_id', $l->id)->filter(fn ($r) => (bool) $r->memenuhi)->sum('n'),
            'positif' => (int) $per->where('lab_id', $l->id)->reject(fn ($r) => (bool) $r->memenuhi)->sum('n'),
        ])->filter(fn ($l) => $l['negatif'] + $l['positif'] > 0)->values();

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
