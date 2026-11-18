<?php

namespace App\Http\Controllers;

use App\Enums\JenisBayar;
use App\Enums\Role;
use App\Enums\Status;
use App\Enums\Tujuan;
use App\Models\Dokumen;
use App\Models\Lhu;
use App\Models\ParameterUji;
use App\Models\Permohonan;
use App\Models\Setting;
use App\Services\LhpWord;
use App\Services\Workflow;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\HeaderUtils;

class PermohonanController extends Controller
{
    public function __construct(private Workflow $wf) {}

    public function index(Request $request)
    {
        $u = $request->user();
        $q = Permohonan::query()->with(['labSaatIni', 'lhu', 'pemohon']);

        if ($u->role === Role::PenggunaJasa) {
            $q->where('user_id', $u->id);
        } elseif ($u->role === Role::Administrasi) {
            $q->whereNotNull('masuk_lab_at');
        }
        $status = Status::tryFrom((string) $request->query('status'));
        if ($status) {
            $q->where('status', $status->value);
        }
        $cari = trim((string) $request->query('cari'));
        if ($cari !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_substr($cari, 0, 60)).'%';
            $q->where(function ($w) use ($like, $u) {
                $w->where('kode', 'like', $like)->orWhere('kode_sampel', 'like', $like)
                    ->orWhere('nama_sampel', 'like', $like)->orWhere('jenis_komoditas', 'like', $like);
                if ($u->role !== Role::PenggunaJasa) {
                    $w->orWhere('pelanggan', 'like', $like)
                        ->orWhereHas('pemohon', fn ($p) => $p->where('instansi', 'like', $like)->orWhere('nama', 'like', $like));
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
            'jenisTerakhir' => request()->user()->tipe_pengguna?->value,
            'labs' => $this->wf->labAktif(),
            'biayaAdmin' => (int) Setting::get('biaya_admin'),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Permohonan::class);
        $data = $request->validate([
            'nama_sampel' => ['required', 'string', 'min:2', 'max:120'],
            'jumlah_sampel' => ['required', 'integer', 'min:1', 'max:500'],
            'pelanggan' => ['required', 'string', 'min:2', 'max:150'],
            'alamat' => ['required', 'string', 'min:5', 'max:300'],
            'jenis_komoditas' => ['required', 'string', 'min:2', 'max:80'],
            'asal_sampel' => ['required', 'string', 'min:2', 'max:150'],
            'jenis_bayar' => ['required', Rule::in(array_map(fn ($j) => $j->value, JenisBayar::publik()))],
            'tujuan' => ['required', Rule::enum(Tujuan::class)],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'dokumen' => ['nullable', 'array', 'max:3'],
            'dokumen.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'pelanggan.required' => 'Nama pelanggan wajib diisi (dicetak di LHP).',
            'alamat.required' => 'Alamat wajib diisi (dicetak di LHP).',
            'jenis_bayar.required' => 'Pilih jenis pengguna jasa: Mandiri, Pengujian Eksternal, atau Surveilen.',
            'dokumen.max' => 'Dokumen pendukung maksimal 3 berkas.',
            'dokumen.*.max' => 'Ukuran tiap berkas maksimal 5 MB.',
            'dokumen.*.mimes' => 'Format berkas harus PDF, JPG, atau PNG.',
        ], [], ['nama_sampel' => 'nama produk', 'pelanggan' => 'nama pelanggan']);

        $p = $this->wf->ajukan($request->user(), collect($data)->except('dokumen')->map(fn ($v) => is_string($v) ? trim($v) : $v)->all(), $request->file('dokumen', []));
        Audit::log('AJUKAN_PERMOHONAN', 'permohonan', $p->id);

        return redirect()->route('permohonan.show', $p)->with('success', "Permohonan {$p->kode} terkirim. Bawa sampel fisik ke loket penerimaan Balai untuk diverifikasi.");
    }

    /** Admin mencatat sampel internal Balai (Monitoring, Test Result, Uji Banding). */
    public function createInternal()
    {
        return view('permohonan.internal', ['labs' => $this->wf->labAktif()]);
    }

    public function storeInternal(Request $request)
    {
        $rows = collect((array) $request->input('sampel', []))
            ->map(fn ($r) => ['jenis_sampel' => trim((string) ($r['jenis_sampel'] ?? '')), 'berat' => trim((string) ($r['berat'] ?? ''))])
            ->filter(fn ($r) => $r['jenis_sampel'] !== '')->values()->all();
        $request->merge(['sampel' => $rows]);
        $data = $request->validate([
            'jenis_bayar' => ['required', Rule::in(array_map(fn ($j) => $j->value, JenisBayar::internalCases()))],
            'pelanggan' => ['required', 'string', 'min:2', 'max:150'],
            'alamat' => ['required', 'string', 'min:5', 'max:300'],
            'no_surat' => ['nullable', 'string', 'max:40'],
            'cp' => ['nullable', 'string', 'max:60'],
            'subkontrak' => ['nullable', 'array'],
            'subkontrak.*' => [Rule::in(JenisBayar::SUBKONTRAK)],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'sampel' => ['required', 'array', 'min:1', 'max:26'],
            'sampel.*.jenis_sampel' => ['required', 'string', 'min:2', 'max:150'],
            'sampel.*.berat' => ['nullable', 'string', 'max:30'],
        ], ['sampel.required' => 'Tulis minimal satu jenis sampel.'], ['pelanggan' => 'nama pelanggan']);

        $p = $this->wf->ajukanInternal($request->user(), $data);
        Audit::log('SAMPEL_INTERNAL', 'permohonan', $p->id, ['layanan' => $data['jenis_bayar']]);

        return redirect()->route('permohonan.show', $p)->with('success', "Sampel {$p->kode_sampel} dicatat dan diteruskan ke lab.");
    }

    public function show(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('view', $permohonan);
        $u = $request->user();
        $p = $permohonan->load(['labSaatIni', 'lhu.validator', 'lhu.pemeriksa', 'hasil.lab', 'hasil.petugas', 'dokumen', 'riwayat.user', 'pemohon', 'tahap.lab', 'tahap.operator', 'sampel.hasil.petugas']);

        $hasil = $p->hasil->sortBy(fn ($h) => $h->lab->urutan)->values();
        $dokumen = $p->dokumen;
        $riwayat = $p->riwayat;
        $lhu = $p->lhu;
        $tampilHasil = true;
        $tampilPemohon = $u->isStaff() || $u->role === Role::Administrasi;

        if ($u->role === Role::PenggunaJasa) {
            // Nilai hasil baru tampil setelah LHP terbit; lampiran internal lab disembunyikan.
            $dokumen = $dokumen->where('jenis', '!=', Dokumen::LAMPIRAN_LAB);
            // Tahapan internal Balai (perpindahan antar-lab, operator, pemeriksaan) tidak diperlihatkan ke pengguna jasa.
            $riwayat = $riwayat
                ->reject(fn ($r) => in_array($r->status, [Status::MenungguValidasi, Status::MenungguCekAdministrasi], true)
                    || ($r->status === Status::DiLab && preg_match('/^(Uji |Sampel ditangani |Dikembalikan |Lab )/u', (string) $r->keterangan)))
                ->map(function ($r) {
                    $r->keterangan = preg_replace('/\.?\s*Sampel diteruskan otomatis ke Lab [^.]*/u', '. Sampel mulai diproses di laboratorium', (string) $r->keterangan);

                    return $r;
                });
            if ($p->status !== Status::Selesai) {
                $tampilHasil = false;
                $lhu = null;
            }
        } elseif ($u->role === Role::PetugasLab) {
            // Uji independen: tanpa identitas klien, tagihan, dan hasil lab lain.
            $lhu = null;
            $hasil = $hasil->where('lab_id', $u->lab_id)->values();
            $dokumen = $dokumen->filter(fn ($d) => $d->jenis === Dokumen::PENDUKUNG || ($d->jenis === Dokumen::LAMPIRAN_LAB && $d->lab_id === $u->lab_id));
            $riwayat = $riwayat->filter(fn ($r) => in_array($r->status, [Status::DiLab, Status::MenungguCekAdministrasi], true))
                ->map(function ($r) {
                    $r->keterangan = preg_replace('/\s*\(pengguna jasa eksternal[^)]*\)|\.?\s*Tagihan Rp[\d.]+(, kode billing SIMPONI \d+)?/u', '', (string) $r->keterangan);

                    return $r;
                });
        } elseif ($u->role === Role::Administrasi) {
            // Administrasi memeriksa isian lab, bukan pembayaran: tanpa bukti bayar, tagihan, kode billing, dan NTPN.
            $dokumen = $dokumen->where('jenis', '!=', Dokumen::BUKTI_BAYAR);
            $riwayat = $riwayat
                ->reject(fn ($r) => in_array($r->status, [Status::MenungguPembayaran, Status::MenungguKonfirmasiBayar], true))
                ->map(function ($r) {
                    $r->keterangan = preg_replace('/\.?\s*Tagihan Rp[\d.]+(, kode billing SIMPONI \d+)?/u', '', (string) $r->keterangan);

                    return $r;
                });
        }

        // Tim penanganan & norma per lab: hanya untuk petugas Balai.
        $tahap = $u->role === Role::PenggunaJasa ? collect() : $p->tahap->sortBy(fn ($t) => $t->lab->urutan)->values();
        $bisaIsi = $this->wf->bolehIsiLab($p, $u);
        $sudahMenangani = $bisaIsi
            && (bool) $tahap->first(fn ($t) => $t->lab_id === $p->current_lab_id && ! $t->selesai_at && $t->operator->contains('id', $u->id));

        // Form isian lab (operator lab ini, atau Admin Lab sebagai kepala tim): katalog parameter lab tempat
        // sampel berada + isian sebelumnya (bila dikembalikan Administrasi).
        $katalog = collect();
        $isianLama = [];
        $catatanKembali = null;
        if ($bisaIsi) {
            $katalog = ParameterUji::query()->where('lab_id', $p->current_lab_id)->aktif()->get()->groupBy('kelompok');
            foreach ($p->sampel as $s) {
                foreach ($s->hasil->where('lab_id', $p->current_lab_id) as $h) {
                    $isianLama[$s->id][$h->parameter_id] = $h;
                }
            }
            $catatanKembali = $p->dikembalikan ? $p->lhu?->catatan_cek : null;
        }

        return view('permohonan.show', [
            'tahap' => $tahap, 'sudahMenangani' => $sudahMenangani,
            'p' => $p, 'u' => $u, 'labs' => $this->wf->labAktif(), 'hasil' => $hasil,
            'dokumen' => $dokumen->values(), 'riwayat' => $riwayat->values(), 'lhu' => $lhu,
            'tampilHasil' => $tampilHasil, 'tampilPemohon' => $tampilPemohon,
            'perkiraanTagihan' => $p->tagihan ?? $this->wf->tagihan($p->jumlah_sampel),
            'katalog' => $katalog, 'isianLama' => $isianLama, 'catatanKembali' => $catatanKembali, 'bisaIsi' => $bisaIsi,
            'penandatangan' => Setting::penandatangan(),
        ]);
    }

    public function verifikasi(Request $request, Permohonan $permohonan)
    {
        Gate::authorize('kelola', $permohonan);
        $request->validate(['keputusan' => ['required', Rule::in(['valid', 'tolak'])]]);

        if ($request->input('keputusan') === 'valid') {
            $jenis = JenisBayar::tryFrom((string) $request->input('jenis_bayar')) ?? $permohonan->jenis_bayar ?? JenisBayar::Mandiri;
            // Rincian sampel dicatat petugas di loket (mis. produk dipecah jadi Air & Es Batu). Baris kosong diabaikan.
            if ($request->has('sampel')) {
                $request->merge(['sampel' => collect((array) $request->input('sampel'))
                    ->map(fn ($r) => ['jenis_sampel' => trim((string) ($r['jenis_sampel'] ?? '')), 'berat' => trim((string) ($r['berat'] ?? ''))])
                    ->filter(fn ($r) => $r['jenis_sampel'] !== '')->values()->all()]);
            }
            $data = $request->validate([
                'jenis_bayar' => ['nullable', Rule::in(array_map(fn ($j) => $j->value, JenisBayar::publik()))],
                'kode_billing' => [Rule::requiredIf($jenis->bayarDiAwal()), 'nullable', 'regex:'.config('sipelab.regex_kode_billing'), Rule::unique('permohonan', 'kode_billing')],
                'pelanggan' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
                'alamat' => ['sometimes', 'required', 'string', 'min:5', 'max:300'],
                'no_surat' => ['nullable', 'string', 'max:40'],
                'cp' => ['nullable', 'string', 'max:60'],
                'subkontrak' => ['nullable', 'array'],
                'subkontrak.*' => [Rule::in(JenisBayar::SUBKONTRAK)],
                'sampel' => ['sometimes', 'array', 'min:1', 'max:26'],
                'sampel.*.jenis_sampel' => ['required', 'string', 'min:2', 'max:150'],
                'sampel.*.berat' => ['nullable', 'string', 'max:30'],
            ], $this->pesanBilling() + [
                'sampel.min' => 'Isi minimal satu sampel.',
                'sampel.max' => 'Maksimal 26 sampel (A sampai Z).',
            ], ['sampel.*.jenis_sampel' => 'jenis sampel']);
            $kode = $jenis->bayarDiAwal() ? $data['kode_billing'] : null;
            $tagihan = $this->wf->verifikasiValid($permohonan, $kode, $request->user(), $jenis,
                ['sampel' => $data['sampel'] ?? null, 'no_surat' => $data['no_surat'] ?? null, 'cp' => $data['cp'] ?? null,
                    'pelanggan' => $data['pelanggan'] ?? null, 'alamat' => $data['alamat'] ?? null,
                    'subkontrak' => implode(', ', $data['subkontrak'] ?? [])]);
            Audit::log('VERIFIKASI_VALID', 'permohonan', $permohonan->id, ['tagihan' => $tagihan, 'jenis_bayar' => $jenis->value]);
            $kodeSampel = $permohonan->fresh()->kode_sampel;

            return back()->with('success', $jenis->bayarDiAwal()
                ? "Sampel terverifikasi dengan kode {$kodeSampel}. Tagihan ".rupiah($tagihan).' dan kode billing dikirim ke pengguna jasa.'
                : "Sampel terverifikasi dengan kode {$kodeSampel} dan langsung diteruskan ke lab. Pengguna jasa eksternal membayar ".rupiah($tagihan).' setelah LHP jadi.');
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
                : 'Pelunasan dikonfirmasi. LHP sekarang bisa diunduh pengguna jasa.');
        }

        $this->wf->tolakPembayaran($permohonan, trim((string) $data['catatan']), $request->user());
        Audit::log('BAYAR_DITOLAK', 'permohonan', $permohonan->id);

        return back()->with('success', 'Bukti pembayaran ditolak. Pengguna jasa diminta mengunggah ulang.');
    }

    /** Operator: centang parameter yang diuji per sampel + hasil tiap ulangan. */
    public function inputHasil(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('view', $permohonan);
        if (Gate::denies('inputHasil', $permohonan)) {
            return back()->with('error', 'Sampel ini tidak ada di antrean lab Anda.');
        }
        $data = $request->validate([
            'tanpa_uji' => ['nullable', 'boolean'],
            'isian' => ['nullable', 'array'],
            'isian.*' => ['array'],
            'isian.*.*' => ['array'],
            'isian.*.*.ulangan' => ['nullable', 'array', 'max:20'],
            'isian.*.*.ulangan.*' => ['nullable', 'string', 'max:60'],
            'isian.*.*.sub' => ['nullable', 'array'],
            'isian.*.*.sub.*' => ['array'],
            'isian.*.*.sub.*.*' => ['nullable', 'string', 'max:20'],
            'isian.*.*.persyaratan' => ['nullable', 'string', 'max:150'],
            'isian.*.*.metode' => ['nullable', 'string', 'max:150'],
            'isian.*.*.keterangan' => ['nullable', 'string', 'max:150'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'lampiran' => ['nullable', 'array', 'max:3'],
            'lampiran.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'lampiran.max' => 'Lampiran maksimal 3 berkas.',
            'lampiran.*.max' => 'Ukuran tiap lampiran maksimal 5 MB.',
            'lampiran.*.mimes' => 'Format lampiran harus PDF, JPG, atau PNG.',
        ]);

        $tanpa = $request->boolean('tanpa_uji');
        $next = $this->wf->inputHasil($permohonan, $request->user(), $request->input('isian', []), $tanpa, $data['catatan'] ?? null, $request->file('lampiran', []));
        Audit::log('INPUT_HASIL', 'permohonan', $permohonan->id, ['tanpa_uji' => $tanpa]);

        return redirect()->route('dashboard')->with('success', $next
            ? "Isian {$permohonan->kode_sampel} tersimpan dan diteruskan ke Lab {$next->nama}."
            : "Isian {$permohonan->kode_sampel} tersimpan. Diteruskan ke Administrasi untuk diperiksa.");
    }

    /** Administrasi: isian operator & file LHP sesuai, atau kembalikan ke lab tertentu. */
    public function cek(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('view', $permohonan);
        Gate::authorize('cek', $permohonan);
        $data = $request->validate([
            'keputusan' => ['required', Rule::in(['sesuai', 'kembalikan'])],
            'lab_id' => ['nullable', 'required_if:keputusan,kembalikan', 'integer'],
            'catatan' => ['nullable', 'required_if:keputusan,kembalikan', 'string', 'max:500'],
        ], [
            'lab_id.required_if' => 'Pilih lab yang perlu memperbaiki isian.',
            'catatan.required_if' => 'Tulis bagian yang perlu diperbaiki supaya operator tahu apa yang harus diubah.',
        ]);

        $lab = $this->wf->cekAdministrasi($permohonan, $request->user(), $data['keputusan'], isset($data['lab_id']) ? (int) $data['lab_id'] : null, $data['catatan'] ?? null);
        Audit::log('CEK_ADMINISTRASI', 'permohonan', $permohonan->id, ['keputusan' => $data['keputusan'], 'lab_id' => $lab?->id]);

        return redirect()->route('dashboard')->with('success', $lab
            ? "{$permohonan->kode_sampel} dikembalikan ke Lab {$lab->nama} untuk diperbaiki."
            : "{$permohonan->kode_sampel} sesuai dan diteruskan ke Admin Lab untuk diterbitkan.");
    }

    public function validasiLhu(Request $request, Permohonan $permohonan)
    {
        Gate::authorize('kelola', $permohonan);
        $daftar = Setting::penandatangan();
        $data = $request->validate([
            'ttd' => ['required', 'integer', 'min:0', 'max:'.max(0, count($daftar) - 1)],
            'jabatan' => ['required', Rule::in([Lhu::JABATAN_KEPALA, Lhu::JABATAN_PJ])],
            'kode_billing' => [Rule::requiredIf($permohonan->eksternal()), 'nullable', 'regex:'.config('sipelab.regex_kode_billing'), Rule::unique('permohonan', 'kode_billing')],
        ], $this->pesanBilling() + ['ttd.required' => 'Pilih penandatangan LHP.']);
        $ttd = $daftar[(int) $data['ttd']] ?? abort(422);
        $kode = $permohonan->eksternal() ? $data['kode_billing'] : null;

        $lhu = $this->wf->validasiLhu($permohonan, $request->user(), $kode, $ttd + ['jabatan' => $data['jabatan']]);
        Audit::log('VALIDASI_LHU', 'permohonan', $permohonan->id, ['nomor' => $lhu->nomor]);

        return back()->with('success', $kode
            ? "LHP {$lhu->nomor} jadi. Kode billing pelunasan dikirim ke pengguna jasa; LHP terbuka setelah lunas."
            : "LHP {$lhu->nomor} diterbitkan.");
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

        return back()->with('success', "Anda tercatat menangani {$permohonan->kode_sampel}.");
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
        abort_if($u->role === Role::Administrasi && $dokumen->jenis === Dokumen::BUKTI_BAYAR, 404);
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

    /** Halaman LHP (HTML) untuk dicetak & dicocokkan dengan QR verifikasi. */
    public function lhu(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('lihatLhu', $permohonan);
        $p = $permohonan->load(['lhu.validator', 'sampel.hasil', 'pemohon', 'hasil.lab']);

        return view('permohonan.lhu', [
            'p' => $p,
            'lhu' => $p->lhu,
            'word' => app(LhpWord::class),
            'utuh' => $this->wf->lhuUtuh($p->lhu),
        ]);
    }

    /** Pratinjau halaman LHP sebelum terbit (Administrasi & Admin). */
    public function pratinjau(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('unduhLhp', $permohonan);
        $p = $permohonan->load(['lhu', 'sampel.hasil', 'pemohon']);

        return view('permohonan.lhu', ['p' => $p, 'lhu' => $p->lhu, 'word' => app(LhpWord::class), 'utuh' => true, 'pratinjau' => true]);
    }

    /** Unduh file Word LHP (format instansi), dibuat dari isian operator. */
    public function lhpWord(Request $request, Permohonan $permohonan, LhpWord $word)
    {
        $this->authorizeOr404('unduhLhp', $permohonan);
        $bin = $word->buat($permohonan);
        Audit::log('UNDUH_LHP_WORD', 'permohonan', $permohonan->id);

        return response($bin, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $word->namaBerkas($permohonan), 'LHP.docx'),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
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
            } elseif ($u->role === Role::Administrasi) {
                $q->where('status', Status::MenungguCekAdministrasi->value);
            }
            $v = $q->max('updated_at').'|'.$q->count();
        }

        return response()->json(['v' => hash('xxh128', $v)])->header('Cache-Control', 'no-store');
    }
}
