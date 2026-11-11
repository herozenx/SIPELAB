<?php

namespace App\Http\Controllers;

use App\Enums\JenisBayar;
use App\Enums\Role;
use App\Enums\Status;
use App\Enums\Tujuan;
use App\Models\Dokumen;
use App\Models\Permohonan;
use App\Services\Workflow;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PermohonanController extends Controller
{
    public function __construct(private Workflow $wf) {}

    public function index(Request $request)
    {
        $u = $request->user();
        $q = Permohonan::query()->with(['labSaatIni', 'lhu', 'pemohon']);

        if ($u->role === Role::PenggunaJasa) {
            $q->where('user_id', $u->id);
        }
        $status = Status::tryFrom((string) $request->query('status'));
        if ($status) {
            $q->where('status', $status->value);
        }
        $cari = trim((string) $request->query('cari'));
        if ($cari !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_substr($cari, 0, 60)).'%';
            $q->where(function ($w) use ($like, $u) {
                $w->where('kode', 'like', $like)->orWhere('nama_sampel', 'like', $like)->orWhere('jenis_komoditas', 'like', $like);
                if ($u->isStaff()) {
                    $w->orWhereHas('pemohon', fn ($p) => $p->where('instansi', 'like', $like)->orWhere('nama', 'like', $like));
                }
            });
        }

        return view('permohonan.index', [
            'rows' => $q->latest('updated_at')->paginate(15)->withQueryString(),
            'status' => $status,
            'cari' => $cari,
            'labs' => $this->wf->labAktif(),
        ]);
    }

    public function create()
    {
        Gate::authorize('create', Permohonan::class);

        return view('permohonan.create', [
            'labs' => $this->wf->labAktif(),
            'biayaAdmin' => (int) \App\Models\Setting::get('biaya_admin'),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Permohonan::class);
        $data = $request->validate([
            'nama_sampel' => ['required', 'string', 'min:2', 'max:120'],
            'jenis_komoditas' => ['required', 'string', 'min:2', 'max:80'],
            'jumlah_sampel' => ['required', 'integer', 'min:1', 'max:500'],
            'asal_sampel' => ['required', 'string', 'min:2', 'max:150'],
            'tujuan' => ['required', Rule::enum(Tujuan::class)],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'dokumen' => ['nullable', 'array', 'max:3'],
            'dokumen.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'dokumen.max' => 'Dokumen pendukung maksimal 3 berkas.',
            'dokumen.*.max' => 'Ukuran tiap berkas maksimal 5 MB.',
            'dokumen.*.mimes' => 'Format berkas harus PDF, JPG, atau PNG.',
        ]);

        $p = $this->wf->ajukan($request->user(), collect($data)->except('dokumen')->map(fn ($v) => is_string($v) ? trim($v) : $v)->all(), $request->file('dokumen', []));
        Audit::log('AJUKAN_PERMOHONAN', 'permohonan', $p->id);

        return redirect()->route('permohonan.show', $p)->with('success', "Permohonan {$p->kode} terkirim. Bawa sampel fisik ke loket penerimaan Balai untuk diverifikasi.");
    }

    public function show(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('view', $permohonan);
        $u = $request->user();
        $p = $permohonan->load(['labSaatIni', 'lhu.validator', 'hasil.lab', 'hasil.petugas', 'dokumen', 'riwayat.user', 'pemohon', 'tahap.lab', 'tahap.operator']);

        $hasil = $p->hasil->sortBy(fn ($h) => $h->lab->urutan)->values();
        $dokumen = $p->dokumen;
        $riwayat = $p->riwayat;
        $lhu = $p->lhu;
        $tampilHasil = true;
        $tampilPemohon = $u->isStaff();

        if ($u->role === Role::PenggunaJasa) {
            // Nilai hasil baru tampil setelah LHU tervalidasi; lampiran internal lab disembunyikan.
            $dokumen = $dokumen->where('jenis', '!=', Dokumen::LAMPIRAN_LAB);
            if ($p->status !== Status::Selesai) {
                $tampilHasil = false;
                $lhu = null;
            }
        } elseif ($u->role === Role::PetugasLab) {
            // Uji independen: tanpa identitas klien, tagihan, dan hasil lab lain.
            $lhu = null;
            $hasil = $hasil->where('lab_id', $u->lab_id)->values();
            $dokumen = $dokumen->filter(fn ($d) => $d->jenis === Dokumen::PENDUKUNG || ($d->jenis === Dokumen::LAMPIRAN_LAB && $d->lab_id === $u->lab_id));
            $riwayat = $riwayat->filter(fn ($r) => in_array($r->status, [Status::DiLab, Status::MenungguValidasi], true));
        }

        $tahap = $p->tahap->sortBy(fn ($t) => $t->lab->urutan)->values();
        $sudahMenangani = $u->role === Role::PetugasLab
            && (bool) $tahap->first(fn ($t) => $t->lab_id === $u->lab_id && ! $t->selesai_at && $t->operator->contains('id', $u->id));

        return view('permohonan.show', [
            'tahap' => $tahap, 'sudahMenangani' => $sudahMenangani,
            'p' => $p, 'u' => $u, 'labs' => $this->wf->labAktif(), 'hasil' => $hasil,
            'dokumen' => $dokumen->values(), 'riwayat' => $riwayat->values(), 'lhu' => $lhu,
            'tampilHasil' => $tampilHasil, 'tampilPemohon' => $tampilPemohon,
            'perkiraanTagihan' => $p->tagihan ?? $this->wf->tagihan($p->jumlah_sampel),
        ]);
    }

    public function verifikasi(Request $request, Permohonan $permohonan)
    {
        Gate::authorize('kelola', $permohonan);
        $request->validate(['keputusan' => ['required', Rule::in(['valid', 'tolak'])]]);

        if ($request->input('keputusan') === 'valid') {
            $jenis = JenisBayar::tryFrom((string) $request->input('jenis_bayar')) ?? $permohonan->jenis_bayar ?? JenisBayar::Mandiri;
            $data = $request->validate([
                'jenis_bayar' => ['nullable', Rule::enum(JenisBayar::class)],
                'kode_billing' => [Rule::requiredIf($jenis === JenisBayar::Mandiri), 'nullable', 'regex:'.config('sipelab.regex_kode_billing'), Rule::unique('permohonan', 'kode_billing')],
            ], $this->pesanBilling());
            $kode = $jenis === JenisBayar::Mandiri ? $data['kode_billing'] : null;
            $tagihan = $this->wf->verifikasiValid($permohonan, $kode, $request->user(), $jenis);
            Audit::log('VERIFIKASI_VALID', 'permohonan', $permohonan->id, ['tagihan' => $tagihan, 'jenis_bayar' => $jenis->value]);

            return back()->with('success', $jenis === JenisBayar::Mandiri
                ? 'Sampel terverifikasi. Tagihan '.rupiah($tagihan).' dan kode billing dikirim ke pengguna jasa.'
                : 'Sampel terverifikasi dan langsung diteruskan ke lab. Pengguna jasa eksternal membayar '.rupiah($tagihan).' setelah LHU jadi.');
        }

        $data = $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:500']], [], ['alasan' => 'alasan penolakan']);
        $this->wf->tolak($permohonan, trim($data['alasan']), $request->user());
        Audit::log('VERIFIKASI_TOLAK', 'permohonan', $permohonan->id);

        return back()->with('success', 'Permohonan ditolak dan alasannya dikirim ke pengguna jasa.');
    }

    public function bayar(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('bayar', $permohonan);
        $request->merge(['ntpn' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('ntpn')))]);
        $data = $request->validate([
            'ntpn' => ['required', 'regex:'.config('sipelab.regex_ntpn'), Rule::unique('permohonan', 'ntpn')->ignore($permohonan->id)],
            'bukti' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'ntpn.regex' => 'NTPN terdiri dari 16 huruf/angka. Salin persis dari bukti setor SIMPONI.',
            'ntpn.unique' => 'NTPN ini sudah dipakai untuk permohonan lain. Periksa kembali bukti pembayaran Anda.',
            'bukti.required' => 'Lampirkan bukti setor SIMPONI (PDF/JPG/PNG).',
            'bukti.max' => 'Ukuran bukti maksimal 5 MB.',
            'bukti.mimes' => 'Format bukti harus PDF, JPG, atau PNG.',
        ]);

        $this->wf->bayar($permohonan, $data['ntpn'], $request->file('bukti'), $request->user());
        Audit::log('UPLOAD_BUKTI_BAYAR', 'permohonan', $permohonan->id);

        return back()->with('success', 'Bukti pembayaran terkirim. Admin akan mengecek dana masuk di SIMPONI.');
    }

    public function konfirmasiBayar(Request $request, Permohonan $permohonan)
    {
        Gate::authorize('kelola', $permohonan);
        $data = $request->validate([
            'keputusan' => ['required', Rule::in(['terima', 'tolak'])],
            'catatan' => ['nullable', 'required_if:keputusan,tolak', 'string', 'max:500'],
        ], ['catatan.required_if' => 'Tulis alasan bukti pembayaran ditolak supaya pengguna jasa bisa memperbaikinya.']);

        if ($data['keputusan'] === 'terima') {
            $lab = $this->wf->terimaPembayaran($permohonan, $request->user());
            Audit::log('BAYAR_DITERIMA', 'permohonan', $permohonan->id);

            return back()->with('success', $lab
                ? "Pembayaran dikonfirmasi. Sampel diteruskan ke Lab {$lab->nama}."
                : 'Pelunasan dikonfirmasi. LHU sekarang bisa diunduh pengguna jasa.');
        }

        $this->wf->tolakPembayaran($permohonan, trim((string) $data['catatan']), $request->user());
        Audit::log('BAYAR_DITOLAK', 'permohonan', $permohonan->id);

        return back()->with('success', 'Bukti pembayaran ditolak. Pengguna jasa diminta mengunggah ulang.');
    }

    public function inputHasil(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('view', $permohonan);
        if (Gate::denies('inputHasil', $permohonan)) {
            return back()->with('error', 'Sampel ini tidak ada di antrean lab Anda.');
        }
        $data = $request->validate([
            'hasil' => ['required', Rule::in(['NEGATIF', 'POSITIF'])],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'lampiran' => ['nullable', 'array', 'max:3'],
            'lampiran.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'hasil.required' => 'Pilih hasil uji: Negatif atau Positif.',
            'lampiran.max' => 'Lampiran maksimal 3 berkas.',
            'lampiran.*.max' => 'Ukuran tiap lampiran maksimal 5 MB.',
            'lampiran.*.mimes' => 'Format lampiran harus PDF, JPG, atau PNG.',
        ]);

        $next = $this->wf->inputHasil($permohonan, $request->user(), $data['hasil'], $data['catatan'] ?? null, $request->file('lampiran', []));
        Audit::log('INPUT_HASIL', 'permohonan', $permohonan->id, ['hasil' => $data['hasil']]);

        return redirect()->route('dashboard')->with('success', $next
            ? "Hasil {$permohonan->kode} tersimpan dan diteruskan ke Lab {$next->nama}."
            : "Hasil {$permohonan->kode} tersimpan. Seluruh uji selesai, menunggu validasi LHU oleh Admin Lab.");
    }

    public function validasiLhu(Request $request, Permohonan $permohonan)
    {
        Gate::authorize('kelola', $permohonan);
        $kode = null;
        if ($permohonan->eksternal()) {
            $kode = $request->validate([
                'kode_billing' => ['required', 'regex:'.config('sipelab.regex_kode_billing'), Rule::unique('permohonan', 'kode_billing')],
            ], $this->pesanBilling())['kode_billing'];
        }
        $lhu = $this->wf->validasiLhu($permohonan, $request->user(), $kode);
        Audit::log('VALIDASI_LHU', 'permohonan', $permohonan->id, ['nomor' => $lhu->nomor]);

        return back()->with('success', $kode
            ? "LHU {$lhu->nomor} jadi. Kode billing pelunasan dikirim ke pengguna jasa; LHU terbuka setelah lunas."
            : "LHU {$lhu->nomor} diterbitkan.");
    }

    /** Operator menyatakan ikut menangani sampel di lab-nya. */
    public function tangani(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('view', $permohonan);
        if (Gate::denies('inputHasil', $permohonan)) {
            return back()->with('error', 'Sampel ini tidak ada di antrean lab Anda.');
        }
        $this->wf->tangani($permohonan, $request->user());
        Audit::log('TANGANI_SAMPEL', 'permohonan', $permohonan->id);

        return back()->with('success', "Anda tercatat menangani {$permohonan->kode}.");
    }

    private function pesanBilling(): array
    {
        return [
            'kode_billing.required' => 'Isi kode billing SIMPONI.',
            'kode_billing.regex' => 'Kode billing SIMPONI harus 15 digit angka.',
            'kode_billing.unique' => 'Kode billing ini sudah dipakai permohonan lain.',
        ];
    }

    /** Unduh berkas lewat route berotorisasi; berkas disimpan di luar folder publik. */
    public function dokumen(Request $request, Permohonan $permohonan, Dokumen $dokumen)
    {
        $this->authorizeOr404('view', $permohonan);
        $u = $request->user();
        abort_if($dokumen->permohonan_id !== $permohonan->id, 404);
        abort_if($u->role === Role::PenggunaJasa && $dokumen->jenis === Dokumen::LAMPIRAN_LAB, 404);
        abort_if($u->role === Role::PetugasLab && ! ($dokumen->jenis === Dokumen::PENDUKUNG
            || ($dokumen->jenis === Dokumen::LAMPIRAN_LAB && $dokumen->lab_id === $u->lab_id)), 404);

        $inline = $request->boolean('lihat') && $dokumen->mime !== 'application/pdf';
        $header = [
            'Content-Type' => $dokumen->mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ];

        // Hosting tanpa disk permanen: isi berkas diambil dari database.
        $isi = DB::table('dokumen_isi')->where('dokumen_id', $dokumen->id)->value('isi');
        if ($isi !== null) {
            Audit::log('UNDUH_DOKUMEN', 'dokumen', $dokumen->id);

            return response((string) base64_decode($isi), 200, $header + [
                'Content-Disposition' => HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $dokumen->original_name, 'berkas.'.pathinfo($dokumen->stored_name, PATHINFO_EXTENSION)),
            ]);
        }

        $path = Workflow::DIR.'/'.basename($dokumen->stored_name);
        abort_unless(Storage::disk(Workflow::DISK)->exists($path), 404);
        Audit::log('UNDUH_DOKUMEN', 'dokumen', $dokumen->id);

        return Storage::disk(Workflow::DISK)->response($path, $dokumen->original_name, $header, $inline ? 'inline' : 'attachment');
    }

    public function lhu(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('lihatLhu', $permohonan);
        $p = $permohonan->load(['lhu.validator', 'hasil.lab', 'pemohon']);

        return view('permohonan.lhu', [
            'p' => $p,
            'lhu' => $p->lhu,
            'hasil' => $p->hasil->sortBy(fn ($h) => $h->lab->urutan)->values(),
            'utuh' => $this->wf->lhuUtuh($p->lhu),
        ]);
    }

    /** Polling ringan untuk pembaruan real-time: hanya sidik versi data, tanpa isi. */
    public function versi(Request $request)
    {
        $u = $request->user();
        $pid = (int) $request->query('p');
        if ($pid) {
            $p = Permohonan::query()->find($pid);
            if (! $p || Gate::denies('view', $p)) {
                return response()->json(['message' => 'Tidak ditemukan'], 404);
            }
            $v = $p->status->value.'|'.$p->updated_at;
        } else {
            $q = Permohonan::query();
            if ($u->role === Role::PenggunaJasa) {
                $q->where('user_id', $u->id);
            } elseif ($u->role === Role::PetugasLab) {
                $q->where('status', Status::DiLab->value)->where('current_lab_id', $u->lab_id);
            }
            $v = $q->max('updated_at').'|'.$q->count();
        }

        return response()->json(['v' => hash('xxh128', $v)])->header('Cache-Control', 'no-store');
    }
}
