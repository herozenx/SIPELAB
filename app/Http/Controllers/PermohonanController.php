<?php

namespace App\Http\Controllers;

use App\Enums\JenisBayar;
use App\Enums\Role;
use App\Enums\Status;
use App\Enums\Tujuan;
use App\Models\Dokumen;
use App\Models\Lab;
use App\Models\Pengembalian;
use App\Models\Permohonan;
use App\Models\Setting;
use App\Models\SuratTugas;
use App\Services\Workflow;
use App\Support\Audit;
use App\Support\Pdf;
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
        $q = Permohonan::query()->with(['lhu', 'pemohon', 'tahap']);

        if ($u->role === Role::PenggunaJasa) {
            $q->milikPerusahaan($u);
        } elseif ($u->isLab()) {
            $q->whereHas('tahap', fn ($t) => $t->where('lab_id', $u->lab_id));
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

    public function create(Request $request)
    {
        Gate::authorize('create', Permohonan::class);

        $u = $request->user();

        return view('permohonan.create', [
            'parameter' => $this->wf->parameterAktif()->groupBy('bidang'),
            'biayaAdmin' => (int) Setting::get('biaya_admin'),
            'pemohon' => $u,
            'alamat' => $this->alamatPemohon($u),
            'maksProduk' => Workflow::MAKS_PRODUK,
        ]);
    }

    /** Alamat dicetak di LHP: diisi saat daftar (akun operator memakai alamat admin perusahaannya). */
    private function alamatPemohon(\App\Models\User $u): ?string
    {
        return $u->alamat ?: $u->induk?->alamat;
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Permohonan::class);
        // Bentuk lama (satu produk: nama_sampel + parameter[]) tetap diterima, dijadikan satu produk.
        if (! $request->has('produk') && $request->hasAny(['nama_sampel', 'parameter'])) {
            $request->merge(['produk' => [[
                'nama' => $request->input('nama_sampel'), 'jumlah' => $request->input('jumlah_sampel', 1),
                'komoditas' => $request->input('jenis_komoditas'), 'berat' => $request->input('berat'),
                'parameter' => $request->input('parameter', []), 'jumlah_uji' => $request->input('jumlah', []),
            ]]]);
        }
        $u = $request->user();
        $alamatAkun = $this->alamatPemohon($u);
        $data = $request->validate([
            'jenis_layanan' => ['required', Rule::in(array_map(fn (JenisBayar $j) => $j->value, JenisBayar::pilihanPengguna()))],
            'produk' => ['required', 'array', 'min:1', 'max:'.Workflow::MAKS_PRODUK],
            'produk.*.komoditas' => ['required', 'string', 'min:2', 'max:80'],
            'produk.*.nama' => ['required', 'string', 'min:2', 'max:120'],
            'produk.*.jumlah' => ['required', 'integer', 'min:1', 'max:500'],
            'produk.*.berat' => ['required', 'string', 'min:1', 'max:30'],
            'produk.*.parameter' => ['required', 'array', 'min:1', 'max:40'],
            'produk.*.parameter.*' => ['integer', 'exists:parameter_uji,id'],
            'produk.*.jumlah_uji' => ['nullable', 'array'],
            'produk.*.jumlah_uji.*' => ['nullable', 'integer', 'min:1', 'max:500'],
            'alamat' => [Rule::requiredIf($alamatAkun === null), 'nullable', 'string', 'min:5', 'max:300'],
            'setuju' => ['required', 'array'],
            'setuju.metode' => ['accepted'],
            'setuju.biaya' => ['accepted'],
            'setuju.waktu' => ['accepted'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'dokumen' => ['nullable', 'array', 'max:3'],
            'dokumen.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'jenis_layanan.required' => 'Pilih tujuan permohonan: Ekspor (Test Result), Uji Mandiri, atau Uji Banding.',
            'alamat.required' => 'Isi alamat usaha/instansi; alamat ini dicetak di LHP.',
            'setuju.required' => 'Centang persetujuan metode, biaya, dan waktu pengujian.',
            'setuju.*.accepted' => 'Centang persetujuan metode, biaya, dan waktu pengujian.',
            'produk.max' => 'Satu permohonan paling banyak '.Workflow::MAKS_PRODUK.' produk. Ajukan sisanya di permohonan baru.',
            'produk.*.komoditas.required' => 'Isi jenis komoditas produk ini.',
            'produk.*.nama.required' => 'Isi nama/jenis sampel produk ini.',
            'produk.*.berat.required' => 'Isi berat sampel produk ini, mis. 500 g.',
            'produk.*.parameter.required' => 'Pilih minimal satu parameter uji untuk produk ini.',
            'produk.*.parameter.min' => 'Pilih minimal satu parameter uji untuk produk ini.',
            'dokumen.max' => 'Dokumen pendukung maksimal 3 berkas.',
            'dokumen.*.max' => 'Ukuran tiap berkas maksimal 5 MB.',
            'dokumen.*.mimes' => 'Format berkas harus PDF, JPG, atau PNG.',
        ], [
            'produk.*.nama' => 'nama sampel', 'produk.*.jumlah' => 'jumlah sampel', 'produk.*.parameter' => 'parameter uji',
            'produk.*.komoditas' => 'jenis komoditas', 'produk.*.berat' => 'berat sampel',
        ]);

        $produk = collect($data['produk'])->values()->map(function ($x) {
            $uji = $x['jumlah_uji'] ?? [];

            return [
                'nama' => trim($x['nama']), 'jumlah' => (int) $x['jumlah'],
                'komoditas' => trim($x['komoditas']), 'berat' => trim($x['berat']),
                // Jumlah pengujian diisi per parameter (bawaan 1), tidak mengikuti jumlah sampel.
                'parameter' => collect($x['parameter'])->unique()->mapWithKeys(fn ($id) => [(int) $id => (int) ($uji[$id] ?? 1)])->all(),
            ];
        })->all();
        $jenis = JenisBayar::from($data['jenis_layanan']);
        $isi = [
            'alamat' => trim((string) ($alamatAkun ?? $data['alamat'])),
            'keterangan' => isset($data['keterangan']) ? trim($data['keterangan']) : null,
            'tujuan' => $jenis->tujuan(),
        ];

        $p = $this->wf->ajukan($u, $isi, $produk, $request->file('dokumen', []), $jenis);
        Audit::log('AJUKAN_PERMOHONAN', 'permohonan', $p->id);

        return redirect()->route('permohonan.show', $p)->with('success', "Permohonan {$p->kodeTampil()} terkirim. Bawa sampel fisik ke loket penerimaan Balai untuk diverifikasi.");
    }

    public function show(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('view', $permohonan);
        $u = $request->user();
        $p = $permohonan->load([
            'lhu.validator', 'produk', 'parameter.lab', 'parameter.penginput', 'parameter.produk', 'parameter.analis', 'dokumen', 'riwayat.user', 'pemohon', 'penerima',
            'tahap.lab', 'tahap.operator', 'suratTugas.lab', 'suratTugas.penyelia', 'persetujuan.user', 'persetujuan.lab',
            'pengembalian.lab', 'pengembalian.pengirim',
        ]);

        $parameter = $p->parameter;
        $dokumen = $p->dokumen;
        $riwayat = $p->riwayat;
        $tahap = $p->tahap->sortBy(fn ($t) => $t->lab->urutan)->values();
        $pengembalian = $p->pengembalian;

        if ($u->role === Role::PenggunaJasa) {
            // LHP rahasia: pengguna jasa hanya melihat status; tahapan & hasil internal lab disembunyikan.
            $dokumen = $dokumen->whereIn('jenis', [Dokumen::PENDUKUNG, Dokumen::BUKTI_BAYAR, Dokumen::BILLING]);
            $riwayat = $riwayat
                ->reject(fn ($r) => $r->status === Status::DiLab || $r->status->tahapLhp()
                    || preg_match('/^(Surat tugas|Pengujian Lab|Sampel ditangani|Dikembalikan)/u', (string) $r->keterangan))
                ->map(function ($r) {
                    $r->keterangan = preg_replace('/\.?\s*Dibayar di belakang; siap dibuatkan surat tugas\./u', '. Sampel segera diproses di laboratorium.', (string) $r->keterangan);
                    $r->keterangan = preg_replace('/\.?\s*Sampel siap dibuatkan surat tugas pengujian/u', '. Sampel segera diproses di laboratorium', (string) $r->keterangan);

                    return $r;
                });
            $tahap = collect();
            $pengembalian = collect();
        } elseif ($u->isLab()) {
            // Uji independen: hanya parameter lab sendiri, tanpa identitas klien dan tagihan.
            $parameter = $parameter->where('lab_id', $u->lab_id)->values();
            $dokumen = $dokumen->filter(fn ($d) => $d->jenis === Dokumen::PENDUKUNG || ($d->jenis === Dokumen::LAMPIRAN_LAB && $d->lab_id === $u->lab_id));
            $riwayat = $riwayat->filter(fn ($r) => $r->status === Status::DiLab || $r->status->tahapLhp());
            $tahap = $tahap->where('lab_id', $u->lab_id)->values();
            $pengembalian = $pengembalian->where('lab_id', $u->lab_id)->values();
        }

        $tahapSaya = $u->isLab() ? $this->wf->tahapTerbuka($p, $u->lab_id) : null;
        $penyeliaPerLab = $p->status === Status::SiapUji
            ? Lab::query()->whereIn('id', $p->labTerlibat())->orderBy('urutan')->with('penyelia')->get()
            : collect();

        return view('permohonan.show', [
            'p' => $p, 'u' => $u, 'labs' => $this->wf->labAktif(),
            'parameter' => $parameter, 'dokumen' => $dokumen->values(), 'riwayat' => $riwayat->values(),
            'tahap' => $tahap, 'tahapSaya' => $tahapSaya, 'pengembalian' => $pengembalian,
            'penyeliaPerLab' => $penyeliaPerLab,
            'analisLab' => $tahapSaya && $u->role === Role::Penyelia
                ? \App\Models\User::query()->where('role', Role::PetugasLab->value)->where('lab_id', $u->lab_id)->where('aktif', true)->orderBy('nama')->get(['id', 'nama'])
                : collect(),
            'sudahMenangani' => $tahapSaya && $tahapSaya->operator->contains('id', $u->id),
            'perkiraanTagihan' => $p->tagihan ?? $this->wf->tagihan($p),
        ]);
    }

    /** Administrator: verifikasi berkas & data permohonan (diterima / ditolak dengan alasan). */
    public function verifikasi(Request $request, Permohonan $permohonan)
    {
        Gate::authorize('verifikasi', $permohonan);
        // Bentuk lama (keputusan valid/tolak, tanpa ceklis) tetap diterima.
        if (! $request->has('kesimpulan') && $request->has('keputusan')) {
            $request->merge(['kesimpulan' => $request->input('keputusan') === 'tolak' ? 'tidak' : 'dapat',
                'kaji' => $request->input('kaji', ['personel' => 1, 'bahan' => 1, 'alat' => 1])]);
        }
        $data = $request->validate([
            'kesimpulan' => ['required', Rule::in(array_keys(Workflow::KESIMPULAN))],
            'kaji' => ['exclude_if:kesimpulan,tidak', 'required', 'array'],
            'kaji.personel' => ['exclude_if:kesimpulan,tidak', 'accepted'],
            'kaji.bahan' => ['exclude_if:kesimpulan,tidak', 'accepted'],
            'kaji.alat' => ['exclude_if:kesimpulan,tidak', 'accepted'],
            'catatan_eksternal' => ['exclude_unless:kesimpulan,eksternal', 'required', 'string', 'max:500'],
        ], [
            'kesimpulan.required' => 'Pilih kesimpulan kaji ulang permintaan.',
            'kaji.required' => 'Lengkapi kaji ulang permintaan: personel, bahan uji, dan alat uji.',
            'kaji.*.accepted' => 'Lengkapi kaji ulang permintaan: personel, bahan uji, dan alat uji harus siap.',
            'catatan_eksternal.required' => 'Tulis laboratorium eksternal dan parameter yang dirujuk.',
        ]);

        if ($data['kesimpulan'] !== 'tidak') {
            $this->wf->verifikasi($permohonan, $request->user(), $data['kesimpulan'], isset($data['catatan_eksternal']) ? trim($data['catatan_eksternal']) : null);
            Audit::log('VERIFIKASI_DITERIMA', 'permohonan', $permohonan->id, ['kesimpulan' => $data['kesimpulan']]);

            return back()->with('success', 'Permohonan diterima. Sampel menunggu registrasi oleh Operator/Admin Laboratorium.');
        }

        $data = $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:500']], [], ['alasan' => 'alasan penolakan']);
        $this->wf->tolak($permohonan, trim($data['alasan']), $request->user());
        Audit::log('VERIFIKASI_TOLAK', 'permohonan', $permohonan->id);

        return back()->with('success', 'Permohonan ditolak dan alasannya dikirim ke pengguna jasa.');
    }

    /** Operator/Admin Lab: terima sampel fisik, kaji ulang, tetapkan jenis layanan, registrasi (kode sampel otomatis). */
    public function registrasi(Request $request, Permohonan $permohonan)
    {
        Gate::authorize('registrasi', $permohonan);
        $data = $request->validate([
            'jenis_bayar' => ['required', Rule::in(array_map(fn (JenisBayar $j) => $j->value, JenisBayar::pilihan()))],
            'bentuk' => ['nullable', 'string', 'max:20'],
            'kemasan' => ['nullable', 'string', 'max:20'],
            'suhu' => ['nullable', 'string', 'max:10'],
            'berat' => ['nullable', 'string', 'max:20'],
        ], [
            'jenis_bayar.required' => 'Tetapkan tujuan permohonan (jenis layanan).',
        ]);
        $jenis = JenisBayar::from($data['jenis_bayar']);
        $kode = $this->wf->registrasi($permohonan, $request->user(), $jenis, collect($data)->only(['bentuk', 'kemasan', 'suhu', 'berat'])->all());
        Audit::log('REGISTRASI_SAMPEL', 'permohonan', $permohonan->id, ['kode_sampel' => $kode, 'jenis_bayar' => $jenis->value]);

        return back()->with('success', "Sampel diregistrasi dengan kode {$kode}. "
            .($jenis->bayarDiAwal() ? 'Menunggu Operator Bendahara menerbitkan billing SIMPONI.' : 'Sampel siap dibuatkan surat tugas oleh Admin Laboratorium.'));
    }

    /** Operator Bendahara: terbitkan billing SIMPONI resmi. */
    public function billing(Request $request, Permohonan $permohonan)
    {
        Gate::authorize('billing', $permohonan);
        $request->merge(['tagihan' => preg_replace('/\D+/', '', (string) $request->input('tagihan'))]);
        $data = $request->validate([
            'kode_billing' => ['required', 'regex:'.config('sipelab.regex_kode_billing'), Rule::unique('permohonan', 'kode_billing')->ignore($permohonan->id)],
            'tagihan' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'billing_at' => ['nullable', 'date'],
            'billing_kedaluwarsa_at' => ['nullable', 'date', 'after_or_equal:billing_at'],
            'nomor_aju' => ['nullable', 'string', 'max:60'],
            'nomor_pnbp' => ['nullable', 'string', 'max:60'],
            'berkas_billing' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], $this->pesanBilling() + ['tagihan.required' => 'Isi jumlah tagihan sesuai billing SIMPONI.', 'tagihan.min' => 'Jumlah tagihan harus lebih dari 0.', 'billing_kedaluwarsa_at.after_or_equal' => 'Tanggal kedaluwarsa tidak boleh sebelum tanggal billing.']);

        $this->wf->terbitkanBilling($permohonan, $data['kode_billing'], (int) $data['tagihan'], $request->file('berkas_billing'), $request->user(),
            collect($data)->only(['billing_at', 'billing_kedaluwarsa_at', 'nomor_aju', 'nomor_pnbp'])->all());
        Audit::log('TERBIT_BILLING', 'permohonan', $permohonan->id, ['kode_billing' => $data['kode_billing'], 'tagihan' => (int) $data['tagihan']]);

        return back()->with('success', 'Billing SIMPONI diterbitkan. Bukti Tagihan PNBP tersedia untuk pengguna jasa.');
    }

    /** Pengguna jasa: unggah bukti pembayaran (tanpa mengetik NTPN). */
    public function bayar(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('bayar', $permohonan);
        $data = $request->validate([
            'bukti' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'bukti.required' => 'Lampirkan bukti setor SIMPONI (PDF/JPG/PNG).',
            'bukti.max' => 'Ukuran bukti maksimal 5 MB.',
            'bukti.mimes' => 'Format bukti harus PDF, JPG, atau PNG.',
        ]);

        $this->wf->bayar($permohonan, $data['bukti'], $request->user());
        Audit::log('UPLOAD_BUKTI_BAYAR', 'permohonan', $permohonan->id);

        return back()->with('success', 'Bukti pembayaran terkirim. Operator Bendahara akan mengecek dana masuk di SIMPONI.');
    }

    /** Operator Bendahara: konfirmasi dana masuk. */
    public function konfirmasiBayar(Request $request, Permohonan $permohonan)
    {
        Gate::authorize('billing', $permohonan);
        $request->merge(['ntpn' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('ntpn')))]);
        $data = $request->validate([
            'keputusan' => ['required', Rule::in(['terima', 'tolak'])],
            'ntpn' => ['nullable', 'regex:'.config('sipelab.regex_ntpn'), Rule::unique('permohonan', 'ntpn')->ignore($permohonan->id)],
            'catatan' => ['nullable', 'required_if:keputusan,tolak', 'string', 'max:500'],
            'dibayar_at' => ['nullable', 'date'],
            'bank_bayar' => ['nullable', 'string', 'max:60'],
            'channel_bayar' => ['nullable', 'string', 'max:40'],
            'ntb' => ['nullable', 'string', 'max:40'],
        ], [
            'catatan.required_if' => 'Tulis alasan bukti pembayaran ditolak supaya pengguna jasa bisa memperbaikinya.',
            'ntpn.regex' => 'NTPN terdiri dari 16 huruf/angka.',
            'ntpn.unique' => 'NTPN ini sudah tercatat untuk permohonan lain.',
        ]);

        if ($data['keputusan'] === 'terima') {
            $ke = $this->wf->terimaPembayaran($permohonan, $request->user(), $data['ntpn'] ?: null, collect($data)->only(['dibayar_at', 'bank_bayar', 'channel_bayar', 'ntb'])->all());
            Audit::log('BAYAR_DITERIMA', 'permohonan', $permohonan->id);

            return back()->with('success', $ke === Status::Selesai
                ? 'Pelunasan dikonfirmasi. LHP selesai dan dapat dicetak untuk diserahkan ke pengguna jasa.'
                : 'Pembayaran dikonfirmasi. Sampel siap dibuatkan surat tugas oleh Admin Laboratorium.');
        }

        $this->wf->tolakPembayaran($permohonan, trim((string) $data['catatan']), $request->user());
        Audit::log('BAYAR_DITOLAK', 'permohonan', $permohonan->id);

        return back()->with('success', 'Bukti pembayaran ditolak. Pengguna jasa diminta mengunggah ulang.');
    }

    /** Admin Laboratorium: terbitkan surat tugas pengujian ke penyelia setiap bidang yang terlibat. */
    public function suratTugas(Request $request, Permohonan $permohonan)
    {
        Gate::authorize('suratTugas', $permohonan);
        $data = $request->validate([
            'penyelia' => ['nullable', 'array'], 'penyelia.*' => ['nullable', 'integer'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);
        $surat = $this->wf->buatSuratTugas($permohonan, $request->user(), array_filter($data['penyelia'] ?? []), $data['catatan'] ?? null);
        Audit::log('SURAT_TUGAS', 'permohonan', $permohonan->id, ['nomor' => $surat->first()?->nomor]);

        return back()->with('success', 'Surat tugas '.$surat->first()?->nomor.' diterbitkan untuk '.$surat->count().' bidang. Sampel masuk antrean lab.');
    }

    /** Surat tugas pengujian (PDF, template FL/OK/SUB II/8.5.3.d), dibuat otomatis dari parameter yang diminta. */
    public function cetakSuratTugas(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('view', $permohonan);
        abort_unless(Gate::allows('cetakSuratTugas', $permohonan), 404);
        $surat = $permohonan->suratTugas()->with(['lab', 'penyelia'])->get()->sortBy(fn ($s) => $s->lab->urutan)->values();
        abort_if($surat->isEmpty(), 404);

        $pj = \App\Models\User::query()->where('role', Role::PenanggungJawab->value)->where('aktif', true)->orderBy('id')->first();
        $penandaTangan = [
            'nama' => Setting::get('nama_kepala_lab') ?: (string) $pj?->nama,
            'nip' => Setting::get('nip_kepala_lab') ?: (string) $pj?->nip,
        ];

        return Pdf::respons('pdf.surat-tugas', [
            'p' => $permohonan->load('produk'), 'surat' => $surat, 'parameter' => $permohonan->parameter()->get(),
            'labs' => \App\Models\Lab::query()->aktif()->get()->merge($surat->pluck('lab'))->unique('id')->sortBy('urutan')->values(),
            'penandaTangan' => $penandaTangan,
        ], 'Surat Tugas '.str_replace('/', '-', (string) $permohonan->kode_sampel).'.pdf', $request->boolean('unduh'));
    }

    /** Analis/Penyelia: simpan hasil parameter lab sendiri; "selesai" menutup pengujian lab itu. */
    public function inputHasil(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('view', $permohonan);
        if (Gate::denies('inputHasil', $permohonan)) {
            return back()->with('error', 'Sampel ini tidak ada di antrean lab Anda.');
        }
        $data = $request->validate([
            'aksi' => ['required', Rule::in(['simpan', 'selesai'])],
            'hasil' => ['nullable', 'array'],
            'hasil.*.hasil' => ['nullable', 'string', 'max:150'],
            'hasil.*.memenuhi' => ['nullable', Rule::in(['0', '1'])],
            'hasil.*.keterangan' => ['nullable', 'string', 'max:300'],
            'lampiran' => ['nullable', 'array', 'max:3'],
            'lampiran.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'lampiran.max' => 'Lampiran maksimal 3 berkas.',
            'lampiran.*.max' => 'Ukuran tiap lampiran maksimal 5 MB.',
            'lampiran.*.mimes' => 'Format lampiran harus PDF, JPG, atau PNG.',
        ]);

        $selesai = $data['aksi'] === 'selesai';
        $semua = $this->wf->simpanHasil($permohonan, $request->user(), $data['hasil'] ?? [], $request->file('lampiran', []), $selesai);
        Audit::log($selesai ? 'HASIL_SELESAI' : 'HASIL_SIMPAN', 'permohonan', $permohonan->id);
        $kode = $permohonan->kodeTampil();

        if (! $selesai) {
            return back()->with('success', "Hasil sementara {$kode} disimpan.");
        }

        return redirect()->route('dashboard')->with('success', $semua
            ? "Pengujian {$kode} selesai di semua lab. Menunggu pemeriksaan LHP oleh Admin Laboratorium."
            : "Pengujian lab Anda untuk {$kode} selesai. Menunggu lab lain.");
    }

    /** Analis menyatakan ikut menangani sampel di lab-nya. */
    public function tangani(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('view', $permohonan);
        if (Gate::denies('inputHasil', $permohonan)) {
            return back()->with('error', 'Sampel ini tidak ada di antrean lab Anda.');
        }
        $this->wf->tangani($permohonan, $request->user());
        Audit::log('TANGANI_SAMPEL', 'permohonan', $permohonan->id);

        return back()->with('success', "Anda tercatat menangani {$permohonan->kodeTampil()}.");
    }

    /** Penyelia: tugaskan analis untuk tiap parameter lab-nya (disposisi). */
    public function disposisi(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('view', $permohonan);
        if ($request->user()->role !== Role::Penyelia || Gate::denies('inputHasil', $permohonan)) {
            return back()->with('error', 'Hanya penyelia lab yang sedang menguji sampel ini yang dapat membuat disposisi.');
        }
        $data = $request->validate(['analis' => ['required', 'array'], 'analis.*' => ['nullable', 'integer']],
            ['analis.required' => 'Pilih analis untuk minimal satu parameter.']);
        $n = $this->wf->disposisi($permohonan, $request->user(), $data['analis']);
        Audit::log('DISPOSISI_ANALIS', 'permohonan', $permohonan->id, ['analis' => $data['analis']]);

        return back()->with('success', $n > 0 ? 'Disposisi analis disimpan.' : 'Tidak ada perubahan disposisi.');
    }

    /**
     * Lembar Disposisi harian (FL/OK/SUB II/8.5.2.a): semua sampel yang masuk lab pada tanggal itu,
     * per produk, dengan analis per bidang dan jumlah uji per parameter.
     */
    public function lembarDisposisi(Request $request)
    {
        $u = $request->user();
        $tanggal = \Illuminate\Support\Carbon::parse($request->query('tanggal', now()->format('Y-m-d')), config('app.timezone'))->startOfDay();
        $q = Permohonan::query()->whereNotNull('kode_sampel')
            ->whereBetween('masuk_lab_at', [$tanggal->copy(), $tanggal->copy()->endOfDay()])
            ->with(['produk', 'parameter.analis', 'parameter.lab', 'tahap.operator']);
        if ($u->isLab()) {
            $q->whereHas('tahap', fn ($t) => $t->where('lab_id', $u->lab_id));
        }
        $rows = $q->get()->sortBy(fn ($p) => [preg_match('#\.(\d+)$#', (string) $p->kode_sampel, $m) ? (int) $m[1] : 0, $p->id])->values();
        $labs = Lab::query()->aktif()->orderBy('urutan')->with('penyelia')->get();
        $kodeParam = $rows->flatMap(fn ($p) => $p->parameter)->sortBy(fn ($x) => [$x->lab?->urutan, $x->parameter_id])
            ->map(fn ($x) => $x->kode ?: $x->nama)->unique()->values();
        Audit::log('CETAK_DISPOSISI', 'permohonan', null, ['tanggal' => $tanggal->format('Y-m-d')]);

        return Pdf::respons('pdf.disposisi', [
            'tanggal' => $tanggal, 'rows' => $rows, 'labs' => $labs, 'kodeParam' => $kodeParam,
        ], 'Lembar Disposisi '.$tanggal->format('Y-m-d').'.pdf', $request->boolean('unduh'), 'landscape');
    }

    /** Admin Laboratorium: periksa kesesuaian data LHP dengan permohonan. */
    public function periksa(Request $request, Permohonan $permohonan)
    {
        Gate::authorize('periksa', $permohonan);
        $data = $request->validate([
            'cek' => ['required', 'array'],
            'cek.identitas' => ['accepted'],
            'cek.parameter' => ['accepted'],
            'cek.hasil' => ['accepted'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ], ['cek.required' => 'Centang semua butir pemeriksaan.', 'cek.*.accepted' => 'Centang semua butir pemeriksaan sebelum meneruskan.']);
        $this->wf->periksa($permohonan, $request->user(), $data['catatan'] ?? null);
        Audit::log('PERIKSA_LHP', 'permohonan', $permohonan->id);

        return back()->with('success', 'Pemeriksaan tercatat. LHP diteruskan ke penyelia untuk diparaf.');
    }

    public function paraf(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('view', $permohonan);
        abort_unless(Gate::allows('paraf', $permohonan), 403);
        // Paraf digambar di layar (rapat 19 Nov) lalu dicetak di LHP: hanya PNG kecil dari kanvas.
        $request->validate(
            ['paraf' => ['required', 'string', 'max:200000', 'starts_with:data:image/png;base64,', function ($attr, $v, $fail) {
                $png = base64_decode(substr((string) $v, 22), true);
                if ($png === false || ! str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
                    $fail('Gambar paraf tidak valid. Gambar ulang paraf Anda.');
                }
            }]],
            ['paraf.required' => 'Gambar paraf Anda di kotak paraf terlebih dahulu.', 'paraf.starts_with' => 'Gambar paraf tidak valid. Gambar ulang paraf Anda.', 'paraf.max' => 'Gambar paraf terlalu besar. Hapus lalu gambar ulang.'],
        );
        $lengkap = $this->wf->paraf($permohonan, $request->user(), (string) $request->input('paraf'));
        Audit::log('PARAF_LHP', 'permohonan', $permohonan->id);

        return back()->with('success', $lengkap ? 'Paraf tercatat. Semua bidang lengkap, LHP menunggu pengesahan.' : 'Paraf tercatat. Menunggu paraf penyelia bidang lain.');
    }

    public function sahkan(Request $request, Permohonan $permohonan)
    {
        Gate::authorize('sahkan', $permohonan);
        $data = $request->validate(
            ['jabatan' => ['required', Rule::in(array_keys(\App\Models\Lhu::JABATAN))]],
            ['jabatan.required' => 'Pilih jabatan penanda tangan LHP.'],
        );
        $lhu = $this->wf->sahkan($permohonan, $request->user(), $data['jabatan']);
        Audit::log('SAHKAN_LHP', 'permohonan', $permohonan->id, ['nomor' => $lhu->nomor]);

        return back()->with('success', "LHP {$lhu->nomor} disahkan."
            .($permohonan->fresh()->status === Status::Selesai ? ' LHP selesai dan dapat dicetak.' : ' Menunggu billing pelunasan dari Operator Bendahara.'));
    }

    /** Kembalikan ke lab: revisi data atau uji ulang, dengan catatan perbaikan. */
    public function kembalikan(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('view', $permohonan);
        Gate::authorize('kembalikan', $permohonan);
        $data = $request->validate([
            'jenis' => ['required', Rule::in([Pengembalian::REVISI, Pengembalian::UJI_ULANG, Pengembalian::PERBAIKAN])],
            'lab_id' => [Rule::requiredIf(fn () => $request->input('jenis') !== Pengembalian::PERBAIKAN), 'nullable', 'integer'],
            'catatan' => ['required', 'string', 'min:5', 'max:1000'],
        ], ['catatan.required' => 'Tulis catatan perbaikan untuk lab.', 'jenis.required' => 'Pilih revisi data atau uji ulang.']);
        $this->wf->kembalikan($permohonan, $request->user(), isset($data['lab_id']) ? (int) $data['lab_id'] : null, $data['jenis'], trim($data['catatan']));
        Audit::log('KEMBALIKAN_LAB', 'permohonan', $permohonan->id, $data);

        return back()->with('success', $data['jenis'] === Pengembalian::PERBAIKAN ? 'Dikembalikan ke validasi hasil (Administrator) beserta catatan perbaikannya.' : 'Proses dikembalikan ke lab beserta catatan perbaikannya.');
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
        abort_if($u->isLab() && ! ($dokumen->jenis === Dokumen::PENDUKUNG
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

    /** LHP: hanya petugas kantor (rahasia). */
    public function lhu(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('lihatLhu', $permohonan);
        $p = $permohonan->load(['lhu.validator', 'produk', 'parameter', 'hasil.lab', 'pemohon', 'tahap', 'persetujuan.user', 'persetujuan.lab']);
        Audit::log('BUKA_LHP', 'permohonan', $p->id);

        return view('permohonan.lhu', [
            'p' => $p,
            'lhu' => $p->lhu,
            'parameter' => $p->parameter->sortBy(fn ($x) => [$x->lab_id, $x->id])->values(),
            'halaman' => $this->halamanLhp($p),
            'hasilLama' => $p->hasil->sortBy(fn ($h) => $h->lab->urutan)->values(),
            'utuh' => $this->wf->lhuUtuh($p->lhu),
        ]);
    }

    /**
     * Satu halaman LHP per produk (nomor LHP sama), seperti dokumen Balai: Kerang Darah Segar (P1) SP.M/020326.08A, dst.
     *
     * @return \Illuminate\Support\Collection<int, array{nama:string, kode:?string, parameter:\Illuminate\Support\Collection}>
     */
    private function halamanLhp(Permohonan $p)
    {
        $urut = fn ($c) => $c->sortBy(fn ($x) => [$x->lab_id, $x->id])->values();
        $halaman = $p->produk->map(fn ($x) => [
            'nama' => $x->nama, 'kode' => $x->kode ?: $p->kode_sampel, 'parameter' => $urut($p->parameter->where('produk_id', $x->id)),
        ])->filter(fn ($h) => $h['parameter']->isNotEmpty())->values();
        $tanpaProduk = $p->parameter->whereNull('produk_id');
        if ($halaman->isEmpty() || $tanpaProduk->isNotEmpty()) {
            $halaman->push(['nama' => $p->nama_sampel, 'kode' => $p->kode_sampel, 'parameter' => $urut($halaman->isEmpty() ? $p->parameter : $tanpaProduk)]);
        }

        return $halaman;
    }

    /** LHP dalam format PDF (otomatis dari data hasil). */
    public function lhpPdf(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('lihatLhu', $permohonan);
        $p = $permohonan->load(['lhu.validator', 'produk', 'parameter', 'hasil.lab', 'pemohon', 'tahap', 'persetujuan.lab']);
        Audit::log('UNDUH_LHP_PDF', 'permohonan', $p->id);

        return Pdf::respons('pdf.lhp', [
            'p' => $p, 'lhu' => $p->lhu,
            'parameter' => $p->parameter->sortBy(fn ($x) => [$x->lab_id, $x->id])->values(),
            'halaman' => $this->halamanLhp($p),
            'hasilLama' => $p->hasil->sortBy(fn ($h) => $h->lab->urutan)->values(),
        ], 'LHP '.str_replace('/', '-', (string) $p->kode_sampel ?: $p->kode).'.pdf', $request->boolean('unduh'));
    }

    /** Bukti Tagihan PNBP (PDF) mengikuti format SIMPONI, dibuat otomatis dari data billing & parameter. */
    public function buktiTagihan(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('view', $permohonan);
        abort_unless($permohonan->kode_billing && ($request->user()->isStaff() || $request->user()->sePerusahaanDengan($permohonan->user_id)), 404);
        $p = $permohonan->load(['parameter', 'pemohon']);

        return Pdf::respons('pdf.bukti-tagihan', ['p' => $p], 'Bukti Tagihan '.$p->kode_billing.'.pdf', $request->boolean('unduh'));
    }

    /** Bukti terima sampel (PDF), tersedia setelah sampel diregistrasi. */
    public function buktiTerima(Request $request, Permohonan $permohonan)
    {
        $this->authorizeOr404('buktiTerima', $permohonan);
        $p = $permohonan->load(['produk', 'parameter.produk', 'pemohon', 'penerima']);

        return Pdf::respons('pdf.bukti-terima', ['p' => $p], 'Bukti Terima '.str_replace('/', '-', (string) $p->kode_sampel).'.pdf', $request->boolean('unduh'));
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
                $q->milikPerusahaan($u);
            } elseif ($u->isLab()) {
                $q->whereHas('tahap', fn ($t) => $t->where('lab_id', $u->lab_id));
            }
            $v = $q->max('updated_at').'|'.$q->count();
        }

        return response()->json(['v' => hash('xxh128', $v)])->header('Cache-Control', 'no-store');
    }
}
