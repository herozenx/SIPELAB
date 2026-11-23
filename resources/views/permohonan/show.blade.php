@extends('layouts.app')
@section('title', $p->kodeTampil())
@section('poll-id', $p->id)
@section('content')
@php
  $S = \App\Enums\Status::class;
  $R = \App\Enums\Role::class;
  $J = \App\Enums\JenisBayar::class;
  $staff = $u->isStaff();
  $pengguna = $u->role === $R::PenggunaJasa;
  $lab = $u->isLab();
  $mandiri = $p->mandiri();
  $lhpTerbit = $p->lhpTerbit();
  $tolakBayar = $pengguna && $p->status === $S::MenungguPembayaran ? $p->riwayat->last(fn ($r) => str_starts_with((string) $r->keterangan, 'Bukti pembayaran ditolak')) : null;
  $bukti = $dokumen->where('jenis', \App\Models\Dokumen::BUKTI_BAYAR)->last();
  $billingDok = $dokumen->where('jenis', \App\Models\Dokumen::BILLING)->last();
  $perBidang = $parameter->groupBy('bidang');
  $adaHasil = $parameter->contains(fn ($x) => $x->hasil !== null);
  $labTerlibat = \App\Models\Lab::query()->whereIn('id', $p->labTerlibat())->orderBy('urutan')->get();
  $bolehKembali = $p->status->tahapLhp() && Gate::allows('kembalikan', $p);
  $paraf = $p->persetujuan->where('jenis', \App\Models\Persetujuan::PARAF);
  $kembaliTerbuka = $pengembalian->whereNull('selesai_at');
@endphp

<a href="{{ $lab ? route('dashboard') : route('permohonan.index') }}" class="no-print inline-flex items-center gap-1 text-sm font-semibold no-underline"><x-ikon n="kembali" class="h-4 w-4" />{{ $lab ? 'Antrean lab' : 'Daftar permohonan' }}</a>

<div class="mt-3 flex flex-wrap items-start justify-between gap-3">
  <div>
    <h1 class="num">{{ $p->kodeTampil() }}</h1>
    <p class="mt-1 text-kabut-600">{{ $p->nama_sampel }}, {{ $p->jumlah_sampel }} sampel @if($p->kode_sampel && $p->kode_sampel !== $p->kode && ! $lab)<span class="text-kabut-400">·</span> <span class="num text-sm">No. permohonan {{ $p->kode }}</span>@endif</p>
  </div>
  <x-status :p="$p" class="mt-1 px-3 py-1.5 text-sm" />
</div>

@if ($pengguna)
  {{-- Tahapan ringkas untuk pengguna jasa --}}
  @php
    $langkah = $mandiri
      ? [['Verifikasi', [$S::Diajukan, $S::Diterima]], ['Pembayaran', [$S::MenungguBilling, $S::MenungguPembayaran, $S::MenungguKonfirmasiBayar]], ['Pengujian', [$S::SiapUji, $S::DiLab]], ['Penyusunan LHP', [$S::MenungguPemeriksaan, $S::MenungguParaf, $S::MenungguPengesahan]], ['LHP selesai', [$S::Selesai]]]
      : [['Verifikasi', [$S::Diajukan, $S::Diterima]], ['Pengujian', [$S::SiapUji, $S::DiLab]], ['Penyusunan LHP', [$S::MenungguPemeriksaan, $S::MenungguParaf, $S::MenungguPengesahan]], ['Pembayaran', [$S::MenungguBilling, $S::MenungguPembayaran, $S::MenungguKonfirmasiBayar]], ['LHP selesai', [$S::Selesai]]];
    $posisi = collect($langkah)->search(fn ($l) => in_array($p->status, $l[1], true));
    $posisi = $p->status === $S::Ditolak ? 0 : ($posisi === false ? 0 : $posisi);
  @endphp
  <ol class="panel mt-6 grid grid-cols-5 gap-px overflow-hidden bg-kabut-200 text-center text-xs" aria-label="Tahapan permohonan">
    @foreach ($langkah as $i => [$nama])
      @php $state = $p->status === $S::Ditolak ? ($i === 0 ? 'fail' : 'todo') : ($p->status === $S::Selesai || $i < $posisi ? 'done' : ($i === $posisi ? 'now' : 'todo')); @endphp
      <li class="bg-white px-2 py-3 {{ $state === 'now' ? 'bg-laut-50' : '' }}" @if($state === 'now') aria-current="step" @endif>
        <span class="mx-auto grid h-7 w-7 place-items-center rounded-full text-xs font-bold {{ match ($state) { 'done' => 'bg-lamun-600 text-white', 'now' => 'bg-laut-600 text-white', 'fail' => 'bg-karang-600 text-white', default => 'bg-kabut-100 text-kabut-500' } }}">@if($state === 'done')<x-ikon n="cek" class="h-4 w-4" />@elseif($state === 'fail')<x-ikon n="silang" class="h-4 w-4" />@else{{ $i + 1 }}@endif</span>
        <span class="mt-1.5 block font-semibold {{ $state === 'todo' ? 'text-kabut-500' : 'text-tinta' }}">{{ $nama }}</span>
      </li>
    @endforeach
  </ol>
@else
  <section class="panel mt-6 px-4 py-6 sm:px-6" aria-label="Jalur uji">
    <div class="overflow-x-auto pb-1"><div class="min-w-[560px]"><x-jalur :p="$p" :labs="$labs" /></div></div>
    @if ($p->target_selesai_at)
      @php
        $selesaiUji = in_array($p->status, [$S::DiLab, $S::SiapUji], true) ? null : ($tahap->max('selesai_at') ?? $p->updated_at);
        [$normaTeks, $normaNada] = \App\Support\HariKerja::status($p->target_selesai_at, $selesaiUji);
      @endphp
      <p class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-kabut-200 pt-4 text-sm">
        <x-ikon n="jam" class="h-4 w-4 text-kabut-500" />
        <span>Norma waktu sampel jadi <span class="font-semibold">{{ \App\Models\Setting::get('norma_total_hari') }} hari kerja</span>: target <span class="font-semibold">{{ tanggal($p->target_selesai_at, false) }}</span></span>
        <span class="badge-{{ $normaNada }}">{{ $normaTeks }}</span>
      </p>
    @endif
  </section>
@endif

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
  <div class="space-y-6">

    {{-- ===================== Tindakan sesuai peran & status ===================== --}}

    @if ($p->status === $S::Ditolak)
      <section class="panel border-l-4 border-karang-600">
        <div class="panel-body">
          <h2>Permohonan ditolak</h2>
          <p class="mt-2 text-sm">{{ $p->alasan_tolak }}</p>
          @if ($pengguna)<p class="mt-3 text-sm text-kabut-600">Perbaiki kekurangannya, lalu <a href="{{ route('permohonan.create') }}">ajukan permohonan baru</a>.</p>@endif
        </div>
      </section>
    @endif

    {{-- 1a. Verifikasi berkas & data permohonan (Administrator) --}}
    @if ($p->status === $S::Diajukan)
      @can('verifikasi', $p)
        <section class="panel ring-2 ring-laut-500">
          <div class="panel-head"><h2>Verifikasi permohonan</h2></div>
          <form method="post" action="{{ route('permohonan.verifikasi', $p) }}" class="panel-body space-y-5" data-guard>
            @csrf
            <p class="text-sm text-kabut-600">Periksa berkas dan data permohonan: data sampel, alamat, parameter uji yang diminta, dan dokumen pendukung.</p>
            <fieldset class="grid gap-2 sm:grid-cols-2">
              <legend class="sr-only">Keputusan</legend>
              <label class="choice"><input type="radio" name="keputusan" value="valid" @checked(old('keputusan', 'valid') === 'valid') data-reveal="valid"><span><span class="block font-semibold">Permohonan diterima</span><span class="text-xs text-kabut-500">Lanjut ke registrasi sampel oleh Operator/Admin Lab</span></span></label>
              <label class="choice"><input type="radio" name="keputusan" value="tolak" @checked(old('keputusan') === 'tolak') data-reveal="tolak"><span><span class="block font-semibold">Tolak permohonan</span><span class="text-xs text-kabut-500">Berkas/data tidak sesuai; pengguna menerima alasannya</span></span></label>
            </fieldset>
            <div data-reveal-target="tolak" hidden>
              <label for="alasan" class="label">Alasan penolakan</label>
              <textarea id="alasan" name="alasan" rows="3" maxlength="500" class="input" placeholder="Jelaskan agar pengguna jasa tahu apa yang harus diperbaiki">{{ old('alasan') }}</textarea>
              @error('alasan')<p class="error">{{ $message }}</p>@enderror
            </div>
            <button class="btn-primary" data-confirm="Simpan keputusan verifikasi untuk {{ $p->kode }}?">Simpan keputusan</button>
          </form>
        </section>
      @endcan
      @if ($pengguna)
        <section class="panel"><div class="panel-body">
          <h2>Permohonan sedang diverifikasi</h2>
          <p class="mt-2 text-sm text-kabut-600">Administrator Balai sedang memeriksa berkas dan data permohonan Anda. Bila diterima, Anda diminta menyerahkan sampel ke loket.</p>
        </div></section>
      @endif
    @endif

    {{-- 1b. Penentuan jenis layanan & registrasi sampel (Operator / Admin Laboratorium) --}}
    @if ($p->status === $S::Diterima)
      @can('registrasi', $p)
        @php $wf = app(\App\Services\Workflow::class); $tgl = now()->format('dmy'); @endphp
        <section class="panel ring-2 ring-laut-500">
          <div class="panel-head"><h2>Registrasi sampel</h2></div>
          <form method="post" action="{{ route('permohonan.registrasi', $p) }}" class="panel-body space-y-5" data-guard>
            @csrf
            <p class="text-sm text-kabut-600">Cocokkan sampel yang diserahkan di loket dengan permohonan dan parameter yang diminta, tetapkan jenis layanan, lalu catat ke buku sampel. Kode sampel dibuat otomatis.</p>
            <fieldset>
              <legend class="label">Jenis layanan</legend>
              <div class="grid gap-2 sm:grid-cols-3">
                @foreach ($J::pilihan() as $jb)
                  <label class="choice"><input type="radio" name="jenis_bayar" value="{{ $jb->value }}" @checked(old('jenis_bayar', $p->jenis_bayar?->value ?? 'MANDIRI') === $jb->value)><span><span class="block font-semibold">{{ $jb->label() }}</span><span class="num block text-xs text-laut-700">{{ $wf->kodeSampelBaru($jb, $tgl) }}</span><span class="text-xs text-kabut-500">{{ $jb->keterangan() }}</span></span></label>
                @endforeach
              </div>
              @error('jenis_bayar')<p class="error">{{ $message }}</p>@enderror
              <p class="hint">Pengguna jasa mengajukan sebagai Mandiri. Tetapkan Pengujian Eksternal atau Surveilen bila sesuai. Kode di bawah tiap pilihan adalah kode sampel yang akan dibuat.</p>
            </fieldset>
            <fieldset>
              <legend class="label">Kaji ulang permintaan</legend>
              <div class="grid gap-2 sm:grid-cols-3">
                @foreach (['personel' => 'Personel laboratorium siap', 'bahan' => 'Bahan uji siap', 'alat' => 'Alat uji siap'] as $k => $l)
                  <label class="choice"><input type="checkbox" name="kaji[{{ $k }}]" value="1" @checked(old("kaji.$k")) class="h-4 w-4 accent-laut-600"><span class="text-sm font-semibold">{{ $l }}</span></label>
                @endforeach
              </div>
              @error('kaji')<p class="error">{{ $message }}</p>@enderror
              @error('kaji.*')<p class="error">{{ $message }}</p>@enderror
            </fieldset>
            <div class="grid gap-4 sm:grid-cols-4">
              <div><label for="bentuk" class="label">Bentuk</label><select id="bentuk" name="bentuk" class="input"><option value="">–</option>@foreach (['Beku', 'Segar', 'Kering', 'Cair', 'Lainnya'] as $o)<option @selected(old('bentuk') === $o)>{{ $o }}</option>@endforeach</select></div>
              <div><label for="kemasan" class="label">Kemasan</label><select id="kemasan" name="kemasan" class="input"><option value="">–</option>@foreach (['Plastik', 'Karton (MC)', 'Kaleng', 'Botol', 'Lainnya'] as $o)<option @selected(old('kemasan') === $o)>{{ $o }}</option>@endforeach</select></div>
              <div><label for="suhu" class="label">Suhu (°C)</label><input id="suhu" name="suhu" value="{{ old('suhu') }}" class="input num" maxlength="10" placeholder="-18"></div>
              <div><label for="berat" class="label">Berat/volume</label><input id="berat" name="berat" value="{{ old('berat') }}" class="input num" maxlength="20" placeholder="500 g"></div>
            </div>
            <button class="btn-primary" data-confirm="Registrasi sampel {{ $p->kode }}? Kode sampel akan dibuat.">Registrasi sampel</button>
          </form>
        </section>
      @endcan
      @if ($pengguna)
        <section class="panel ring-2 ring-laut-500"><div class="panel-body">
          <h2>Permohonan diterima: serahkan sampel ke loket</h2>
          <p class="mt-2 text-sm text-kabut-600">Bawa {{ $p->jumlah_sampel }} sampel {{ $p->nama_sampel }} ke loket penerimaan Balai dan sebutkan kode <span class="kode">{{ $p->kode }}</span>. Petugas meregistrasi sampel ke buku sampel; bukti terima sampel bisa diunduh di halaman ini.</p>
        </div></section>
      @endif
    @endif

    {{-- 2. Billing SIMPONI (Operator Bendahara) --}}
    @if ($p->status === $S::MenungguBilling)
      @can('billing', $p)
        <section class="panel ring-2 ring-laut-500">
          <div class="panel-head"><h2>{{ $lhpTerbit ? 'Terbitkan billing pelunasan' : 'Terbitkan billing SIMPONI' }}</h2></div>
          <form method="post" action="{{ route('permohonan.billing', $p) }}" enctype="multipart/form-data" class="panel-body space-y-5" data-guard>
            @csrf
            <p class="text-sm text-kabut-600">{{ $lhpTerbit ? 'LHP sudah disahkan. Layanan '.$p->jenis_bayar->label().' dibayar di belakang.' : 'Layanan Mandiri dibayar sebelum sampel diuji.' }} Buat billing di SIMPONI, lalu salin datanya di sini. Sistem membuat <b>Bukti Tagihan PNBP</b> otomatis (detail tarif per parameter) untuk pengguna jasa.</p>
            <div class="grid gap-5 sm:grid-cols-2">
              <div>
                <label for="kode_billing" class="label">Kode billing SIMPONI</label>
                <input id="kode_billing" name="kode_billing" value="{{ old('kode_billing') }}" class="input num" inputmode="numeric" maxlength="15" placeholder="15 digit angka" required @error('kode_billing') aria-invalid="true" @enderror>
                @error('kode_billing')<p class="error">{{ $message }}</p>@enderror
              </div>
              <div>
                <label for="tagihan" class="label">Jumlah tagihan (Rp)</label>
                <input id="tagihan" name="tagihan" value="{{ old('tagihan', $perkiraanTagihan) }}" class="input num" inputmode="numeric" required @error('tagihan') aria-invalid="true" @enderror>
                @error('tagihan')<p class="error">{{ $message }}</p>@enderror
                <p class="hint">Perkiraan sistem: {{ rupiah($perkiraanTagihan) }}. Sesuaikan dengan billing resmi.</p>
              </div>
              <div>
                <label for="billing_at" class="label">Tgl. billing</label>
                <input id="billing_at" name="billing_at" type="datetime-local" value="{{ old('billing_at', now()->format('Y-m-d\TH:i')) }}" class="input num">
                @error('billing_at')<p class="error">{{ $message }}</p>@enderror
              </div>
              <div>
                <label for="billing_kedaluwarsa_at" class="label">Tgl. kedaluwarsa</label>
                <input id="billing_kedaluwarsa_at" name="billing_kedaluwarsa_at" type="datetime-local" value="{{ old('billing_kedaluwarsa_at', now()->addDays(3)->format('Y-m-d\TH:i')) }}" class="input num">
                @error('billing_kedaluwarsa_at')<p class="error">{{ $message }}</p>@enderror
              </div>
              <div>
                <label for="nomor_aju" class="label">Nomor aju <span class="font-normal text-kabut-500">(opsional)</span></label>
                <input id="nomor_aju" name="nomor_aju" value="{{ old('nomor_aju') }}" class="input num" maxlength="60" placeholder="AN16.0XI2026xxxxx">
              </div>
              <div>
                <label for="nomor_pnbp" class="label">Nomor PNBP <span class="font-normal text-kabut-500">(opsional)</span></label>
                <input id="nomor_pnbp" name="nomor_pnbp" value="{{ old('nomor_pnbp') }}" class="input num" maxlength="60" placeholder="PN/16.0/XI/2026/xxxxx">
              </div>
              <div class="sm:col-span-2">
                <label for="berkas_billing" class="label">Berkas billing dari SIMPONI <span class="font-normal text-kabut-500">(opsional)</span></label>
                <input id="berkas_billing" name="berkas_billing" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-laut-50 file:px-4 file:py-2.5 file:font-semibold file:text-laut-700" data-max-files="1" data-max-size="5242880">
                @error('berkas_billing')<p class="error">{{ $message }}</p>@enderror
              </div>
            </div>
            <button class="btn-primary" data-confirm="Terbitkan billing untuk {{ $p->kodeTampil() }}?">Terbitkan billing</button>
          </form>
        </section>
      @endcan
      @if ($pengguna)
        <section class="panel"><div class="panel-body flex items-start gap-4">
          <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-surya-50 text-surya-700"><x-ikon n="jam" class="h-5 w-5" /></span>
          <div><h2>{{ $lhpTerbit ? 'LHP selesai, menunggu kode billing' : 'Menunggu kode billing' }}</h2><p class="mt-1 text-sm text-kabut-600">{{ $lhpTerbit ? 'Pengujian dan LHP sudah selesai. ' : 'Sampel sudah diverifikasi dengan kode '.$p->kode_sampel.'. ' }}Operator Bendahara sedang menerbitkan billing SIMPONI resmi. Halaman ini diperbarui otomatis.</p></div>
        </div></section>
      @endif
    @endif

    {{-- 3. Pembayaran (pengguna jasa): lihat billing resmi, unggah bukti (tanpa NTPN) --}}
    @if ($pengguna && $p->status === $S::MenungguPembayaran)
      <section class="panel ring-2 ring-laut-500">
        <div class="panel-head"><h2>{{ $lhpTerbit ? 'LHP selesai: lakukan pembayaran' : 'Bayar lewat SIMPONI' }}</h2></div>
        <div class="panel-body space-y-5">
          @if ($tolakBayar)
            <div class="alert-error"><x-ikon n="silang" class="mt-0.5 h-4 w-4 shrink-0" /><div>{{ $tolakBayar->keterangan }}. Unggah ulang bukti yang benar.</div></div>
          @endif
          <dl class="grid gap-4 rounded-lg bg-laut-50 p-4 sm:grid-cols-2">
            <div><dt class="text-xs font-semibold text-laut-600">Kode billing</dt><dd class="num mt-0.5 text-lg font-extrabold tracking-wide" data-copy>{{ $p->kode_billing }}</dd></div>
            <div><dt class="text-xs font-semibold text-laut-600">Jumlah setor</dt><dd class="num mt-0.5 text-lg font-extrabold">{{ rupiah($p->tagihan) }}</dd></div>
          </dl>
          @if ($p->billing_kedaluwarsa_at)<p class="text-sm">Bayar sebelum <span class="font-semibold">{{ tanggal($p->billing_kedaluwarsa_at) }} WIB</span>.</p>@endif
          <span class="flex flex-wrap gap-2">
            <a href="{{ route('permohonan.bukti-tagihan', $p) }}" target="_blank" rel="noopener" class="btn-primary btn-sm"><x-ikon n="berkas" class="h-4 w-4" />Lihat billing resmi (Bukti Tagihan PNBP)</a>
            @if ($billingDok)<a href="{{ route('permohonan.dokumen', [$p, $billingDok]) }}{{ $billingDok->mime !== 'application/pdf' ? '?lihat=1' : '' }}" target="_blank" rel="noopener" class="btn-ghost btn-sm">Berkas dari SIMPONI</a>@endif
          </span>
          <p class="text-sm text-kabut-600">Setor melalui teller bank, ATM, internet/mobile banking, atau kantor pos yang melayani SIMPONI. Setelah berhasil, unggah bukti setornya. Nomor NTPN tidak perlu diketik: petugas membacanya dari bukti setor.</p>
          <form method="post" action="{{ route('permohonan.bayar', $p) }}" enctype="multipart/form-data" class="space-y-4" data-guard>
            @csrf
            <div>
              <label for="bukti" class="label">Bukti setor</label>
              <input id="bukti" name="bukti" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-laut-50 file:px-4 file:py-2.5 file:font-semibold file:text-laut-700" data-max-files="1" data-max-size="5242880">
              @error('bukti')<p class="error">{{ $message }}</p>@enderror
              <p class="hint">PDF, JPG, atau PNG, maksimal 5 MB.</p>
            </div>
            <button class="btn-primary" data-confirm="Kirim bukti pembayaran?"><x-ikon n="unggah" class="h-4 w-4" />Kirim bukti pembayaran</button>
          </form>
        </div>
      </section>
    @elseif (! $pengguna && $p->status === $S::MenungguPembayaran)
      <section class="panel"><div class="panel-body">
        <h2>Menunggu pembayaran pengguna jasa</h2>
        <p class="mt-2 text-sm text-kabut-600">Billing <span class="num font-semibold">{{ $p->kode_billing }}</span> sebesar <span class="num font-semibold">{{ rupiah($p->tagihan) }}</span> sudah diterbitkan.</p>
      </div></section>
    @endif

    @if ($pengguna && $p->status === $S::MenungguKonfirmasiBayar)
      <section class="panel"><div class="panel-body">
        <h2>Pembayaran sedang dicek</h2>
        <p class="mt-2 text-sm text-kabut-600">Operator Bendahara mencocokkan bukti setor dengan dana masuk di SIMPONI. {{ $lhpTerbit ? 'Setelah terkonfirmasi, LHP dapat diambil di kantor Balai.' : 'Setelah terkonfirmasi, sampel mulai diproses di laboratorium.' }}</p>
      </div></section>
    @endif

    {{-- 4. Konfirmasi pembayaran (Operator Bendahara) --}}
    @if ($p->status === $S::MenungguKonfirmasiBayar)
      @can('billing', $p)
        <section class="panel ring-2 ring-laut-500">
          <div class="panel-head"><h2>Konfirmasi pembayaran SIMPONI</h2></div>
          <div class="panel-body space-y-5">
            <dl class="grid gap-4 sm:grid-cols-2">
              <div><dt class="text-xs text-kabut-500">Kode billing</dt><dd class="num mt-0.5 font-bold">{{ $p->kode_billing }}</dd></div>
              <div><dt class="text-xs text-kabut-500">Jumlah tagihan</dt><dd class="num mt-0.5 font-bold">{{ rupiah($p->tagihan) }}</dd></div>
            </dl>
            @if ($bukti)
              <a href="{{ route('permohonan.dokumen', [$p, $bukti]) }}{{ $bukti->mime !== 'application/pdf' ? '?lihat=1' : '' }}" target="_blank" rel="noopener" class="btn-ghost btn-sm"><x-ikon n="berkas" class="h-4 w-4" />Buka bukti setor ({{ ukuran_berkas($bukti->size) }})</a>
            @endif
            <form method="post" action="{{ route('permohonan.konfirmasi', $p) }}" class="space-y-4" data-guard>
              @csrf
              <fieldset class="grid gap-2 sm:grid-cols-2">
                <legend class="sr-only">Keputusan</legend>
                <label class="choice"><input type="radio" name="keputusan" value="terima" @checked(old('keputusan', 'terima') === 'terima') data-reveal="terima"><span><span class="block font-semibold">Dana sudah masuk</span><span class="text-xs text-kabut-500">{{ $lhpTerbit ? 'LHP selesai, siap diserahkan' : 'Sampel siap dibuatkan surat tugas' }}</span></span></label>
                <label class="choice"><input type="radio" name="keputusan" value="tolak" @checked(old('keputusan') === 'tolak') data-reveal="tolak"><span><span class="block font-semibold">Bukti tidak valid</span><span class="text-xs text-kabut-500">Minta pengguna mengunggah ulang</span></span></label>
              </fieldset>
              <div data-reveal-target="terima" class="grid gap-4 sm:grid-cols-2">
                <p class="text-xs text-kabut-500 sm:col-span-2">Data pembayaran dari SIMPONI (opsional), dicetak di Bukti Tagihan PNBP.</p>
                <div><label for="dibayar_at" class="label">Tgl pembayaran</label><input id="dibayar_at" name="dibayar_at" type="datetime-local" value="{{ old('dibayar_at', now()->format('Y-m-d\TH:i')) }}" class="input num"></div>
                <div><label for="bank_bayar" class="label">Bank / Pos bayar</label><input id="bank_bayar" name="bank_bayar" value="{{ old('bank_bayar') }}" class="input" maxlength="60" placeholder="mis. BANK BRI"></div>
                <div><label for="channel_bayar" class="label">Channel bayar</label><input id="channel_bayar" name="channel_bayar" value="{{ old('channel_bayar') }}" class="input" maxlength="40" placeholder="mis. Teller, ATM, Mobile banking"></div>
                <div><label for="ntb" class="label">NTB</label><input id="ntb" name="ntb" value="{{ old('ntb') }}" class="input num" maxlength="40"></div>
                <div class="sm:col-span-2"><label for="ntpn" class="label">NTPN</label><input id="ntpn" name="ntpn" value="{{ old('ntpn') }}" class="input num max-w-xs uppercase placeholder:normal-case" maxlength="24" placeholder="16 huruf/angka">@error('ntpn')<p class="error">{{ $message }}</p>@enderror</div>
              </div>
              <div data-reveal-target="tolak" hidden>
                <label for="catatan" class="label">Alasan bukti ditolak</label>
                <textarea id="catatan" name="catatan" rows="2" maxlength="500" class="input" placeholder="Contoh: dana belum masuk di SIMPONI">{{ old('catatan') }}</textarea>
                @error('catatan')<p class="error">{{ $message }}</p>@enderror
              </div>
              <button class="btn-primary" data-confirm="Simpan keputusan pembayaran untuk {{ $p->kodeTampil() }}?">Simpan keputusan</button>
            </form>
          </div>
        </section>
      @endcan
    @endif

    {{-- 5. Surat tugas (Operator) --}}
    @if ($p->status === $S::SiapUji)
      @can('suratTugas', $p)
        <section class="panel ring-2 ring-laut-500">
          <div class="panel-head"><h2>Terbitkan surat tugas pengujian</h2></div>
          <form method="post" action="{{ route('permohonan.surat', $p) }}" class="panel-body space-y-4" data-guard>
            @csrf
            <p class="text-sm text-kabut-600">Surat tugas dibuat otomatis mengikuti template Laboratorium Penguji: No. FPPC dari kode sampel <span class="num font-semibold">{{ $p->kode_sampel }}</span>, bidang dicentang sesuai parameter yang diminta pengguna jasa, dan ditujukan kepada penyelia setiap bidang. Setelah diterbitkan, sampel masuk antrean semua lab terkait sekaligus.</p>
            <ul class="divide-y divide-kabut-100 rounded-lg border border-kabut-200">
              @foreach ($penyeliaPerLab as $l)
                <li class="grid gap-3 px-4 py-3 sm:grid-cols-[1fr_240px] sm:items-center">
                  <span><span class="font-semibold">Lab {{ $l->nama }}</span><span class="block text-xs text-kabut-500">{{ $p->parameter->where('lab_id', $l->id)->map(fn ($x) => $x->nama.' ×'.$x->jumlah)->join(', ') }}</span></span>
                  @if ($l->penyelia->isEmpty())
                    <span class="text-xs text-karang-600">Belum ada penyelia di lab ini. Tambahkan di menu Tim lab.</span>
                  @else
                    <label class="sr-only" for="penyelia-{{ $l->id }}">Penyelia Lab {{ $l->nama }}</label>
                    <select id="penyelia-{{ $l->id }}" name="penyelia[{{ $l->id }}]" class="input">
                      @foreach ($l->penyelia as $py)<option value="{{ $py->id }}">{{ $py->nama }}</option>@endforeach
                    </select>
                  @endif
                </li>
              @endforeach
            </ul>
            <div><label for="catatan_st" class="label">Catatan <span class="font-normal text-kabut-500">(opsional, dicetak di surat tugas)</span></label><textarea id="catatan_st" name="catatan" rows="2" maxlength="1000" class="input">{{ old('catatan') }}</textarea></div>
            <button class="btn-primary" data-confirm="Terbitkan surat tugas untuk {{ $p->kodeTampil() }}?"><x-ikon n="surat" class="h-4 w-4" />Terbitkan surat tugas</button>
          </form>
        </section>
      @endcan
    @endif

    {{-- Pengguna jasa: sedang diproses --}}
    @if ($pengguna && (in_array($p->status, [$S::SiapUji, $S::DiLab], true) || $p->status->tahapLhp()))
      <section class="panel"><div class="panel-body flex items-start gap-4">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-laut-50 text-laut-600"><x-ikon n="jam" class="h-5 w-5" /></span>
        <div>
          <h2>{{ $p->status->tahapLhp() ? 'Penyusunan LHP' : 'Sampel sedang diproses/pengujian' }}</h2>
          <p class="mt-1 text-sm text-kabut-600">{{ $p->status->tahapLhp() ? 'Pengujian selesai. LHP sedang diperiksa dan disahkan oleh pejabat berwenang.' : 'Sampel Anda (kode '.$p->kode_sampel.') sedang diuji di laboratorium Balai.' }} Halaman ini diperbarui otomatis.{{ $mandiri ? '' : ' Tagihan diterbitkan setelah LHP disahkan.' }}</p>
          @if ($p->target_selesai_at)
            <p class="mt-3 text-sm">Perkiraan selesai: <span class="font-semibold">{{ tanggal($p->target_selesai_at, false) }}</span> <span class="text-kabut-500">({{ \App\Models\Setting::get('norma_total_hari') }} hari kerja)</span></p>
          @endif
        </div>
      </div></section>
    @endif

    {{-- 6. Input hasil per parameter (Analis/Penyelia lab) --}}
    @if ($tahapSaya)
      @php $balik = $kembaliTerbuka->last(); @endphp
      <section class="panel ring-2 ring-laut-500">
        <div class="panel-head"><h2>Input hasil Lab {{ $u->lab->nama }}</h2><span class="flex items-center gap-3"><span class="text-xs text-kabut-500">Batas {{ tanggal($tahapSaya->batas_at, false) }}</span>@if($p->suratTugas->isNotEmpty())<a href="{{ route('permohonan.surat-tugas', $p) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm"><x-ikon n="berkas" class="h-4 w-4" />Surat tugas</a>@endif</span></div>
        @if ($balik)
          <div class="alert-warning m-5 mb-0"><x-ikon n="putar" class="mt-0.5 h-4 w-4 shrink-0" /><div><span class="font-semibold">{{ $balik->label() }}</span> diminta {{ $balik->pengirim->nama }}: {{ $balik->catatan }}</div></div>
        @endif
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-kabut-200 bg-kabut-50 px-5 py-3 {{ $balik ? 'mt-5' : '' }}">
          @if ($sudahMenangani)
            <p class="flex items-center gap-2 text-sm font-semibold text-lamun-700"><x-ikon n="cek" class="h-4 w-4" />Anda tercatat menangani sampel ini.</p>
          @else
            <p class="text-sm text-kabut-600">Klik <span class="font-semibold">Tangani</span> agar nama Anda tercatat sebagai analis sampel ini.</p>
            <form method="post" action="{{ route('permohonan.tangani', $p) }}" data-guard>@csrf<button class="btn-ghost btn-sm"><x-ikon n="tangan" class="h-4 w-4" />Tangani sampel</button></form>
          @endif
        </div>
        <form method="post" action="{{ route('permohonan.hasil', $p) }}" enctype="multipart/form-data" class="panel-body space-y-5" data-guard>
          @csrf
          @foreach ($parameter as $x)
            <fieldset class="rounded-lg border border-kabut-200 p-4">
              <legend class="px-1 text-sm font-bold">{{ $x->kode ? $x->kode.' · ' : '' }}{{ $x->nama }} <span class="font-normal text-kabut-500">×{{ $x->jumlah }}</span></legend>
              <p class="text-xs text-kabut-500">Metode {{ $x->metode }} · Persyaratan: <span class="font-semibold text-tinta">{{ $x->persyaratan ?: '–' }}</span>@if($x->satuan) · Satuan {{ $x->satuan }}@endif</p>
              <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_auto]">
                <div>
                  <label for="hasil-{{ $x->id }}" class="label">Hasil</label>
                  <input id="hasil-{{ $x->id }}" name="hasil[{{ $x->id }}][hasil]" value="{{ old("hasil.{$x->id}.hasil", $x->hasil) }}" class="input" maxlength="150" placeholder="mis. Negatif/25 g, ND, 7,0">
                </div>
                <div>
                  <span class="label">Persyaratan</span>
                  <div class="flex gap-2">
                    @php $m = old("hasil.{$x->id}.memenuhi", $x->memenuhi === null ? '' : ($x->memenuhi ? '1' : '0')); @endphp
                    <label class="choice px-3 py-2 has-[:checked]:bg-lamun-50"><input type="radio" name="hasil[{{ $x->id }}][memenuhi]" value="1" @checked($m === '1')><span class="text-sm font-semibold text-lamun-700">Memenuhi</span></label>
                    <label class="choice px-3 py-2 has-[:checked]:bg-karang-50"><input type="radio" name="hasil[{{ $x->id }}][memenuhi]" value="0" @checked($m === '0')><span class="text-sm font-semibold text-karang-600">Tidak</span></label>
                  </div>
                </div>
              </div>
              <div class="mt-3">
                <label for="ket-{{ $x->id }}" class="label">Keterangan <span class="font-normal text-kabut-500">(opsional, dicetak di LHP)</span></label>
                <input id="ket-{{ $x->id }}" name="hasil[{{ $x->id }}][keterangan]" value="{{ old("hasil.{$x->id}.keterangan", $x->keterangan) }}" class="input" maxlength="300" placeholder="mis. LoD 6,03 mg/kg; LoQ 19,91 mg/kg">
              </div>
              @if ($x->diinput_at)<p class="mt-2 text-xs text-kabut-500">Terakhir diisi {{ $x->penginput?->nama }}, {{ tanggal($x->diinput_at) }}</p>@endif
            </fieldset>
          @endforeach
          <div>
            <label for="lampiran" class="label">Lampiran <span class="font-normal text-kabut-500">(opsional)</span></label>
            <input id="lampiran" name="lampiran[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-laut-50 file:px-4 file:py-2.5 file:font-semibold file:text-laut-700" data-max-files="3" data-max-size="5242880">
            <p class="hint">Lembar kerja atau foto, maksimal 3 berkas, masing-masing 5 MB. Hanya terlihat oleh lab Anda dan petugas kantor.</p>
            @error('lampiran')<p class="error">{{ $message }}</p>@enderror
            @error('lampiran.*')<p class="error">{{ $message }}</p>@enderror
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="btn-ghost" name="aksi" value="simpan">Simpan sementara</button>
            <button class="btn-primary" name="aksi" value="selesai" data-confirm="Selesaikan pengujian Lab {{ $u->lab->nama }}? Semua parameter harus sudah terisi.">Selesai &amp; kirim ke pemeriksaan</button>
          </div>
        </form>
      </section>
    @elseif ($lab && $p->status === $S::DiLab)
      <div class="alert-warning"><x-ikon n="info" class="mt-0.5 h-4 w-4 shrink-0" />Pengujian Lab {{ $u->lab?->nama }} untuk sampel ini sudah selesai. Menunggu lab lain.</div>
    @endif

    {{-- 7. Validasi hasil pengujian (Administrator) --}}
    @if ($p->status === $S::MenungguPemeriksaan)
      @php $perbaikan = $p->pengembalian->where('jenis', \App\Models\Pengembalian::PERBAIKAN)->whereNull('selesai_at')->last(); @endphp
      @can('periksa', $p)
        <section class="panel ring-2 ring-laut-500">
          <div class="panel-head"><h2>Validasi hasil pengujian</h2></div>
          @if ($perbaikan)
            <div class="alert-warning m-5 mb-0"><x-ikon n="putar" class="mt-0.5 h-4 w-4 shrink-0" /><div><span class="font-semibold">Perlu perbaikan data</span> (dari {{ $perbaikan->pengirim->nama }}): {{ $perbaikan->catatan }}</div></div>
          @endif
          <form method="post" action="{{ route('permohonan.periksa', $p) }}" class="panel-body space-y-4" data-guard>
            @csrf
            <p class="text-sm text-kabut-600">Periksa kesesuaian hasil dengan permohonan dan pastikan seluruh parameter sudah terisi. Bila benar dan lengkap, hasil diteruskan ke penyelia untuk diparaf. <a href="#hasil">Lihat tabel hasil</a>.</p>
            <div class="grid gap-2">
              @foreach (['identitas' => 'Identitas pelanggan, kode sampel, dan jenis sampel sesuai permohonan', 'parameter' => 'Seluruh parameter dan jumlah pengujian yang diminta sudah terisi', 'hasil' => 'Hasil, persyaratan mutu, metode acuan, dan keterangan sudah benar'] as $k => $l)
                <label class="choice"><input type="checkbox" name="cek[{{ $k }}]" value="1" @checked(old("cek.$k")) class="h-4 w-4 accent-laut-600"><span class="text-sm">{{ $l }}</span></label>
              @endforeach
              @error('cek')<p class="error">{{ $message }}</p>@enderror
              @error('cek.*')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div><label for="catatan_periksa" class="label">Catatan <span class="font-normal text-kabut-500">(opsional)</span></label><input id="catatan_periksa" name="catatan" maxlength="500" class="input" value="{{ old('catatan') }}"></div>
            <button class="btn-primary" data-confirm="Hasil {{ $p->kodeTampil() }} benar dan lengkap, teruskan ke penyelia?"><x-ikon n="cek" class="h-4 w-4" />Benar &amp; lengkap, teruskan ke penyelia</button>
            <p class="text-xs text-kabut-500">Data belum benar atau belum lengkap? Gunakan <a href="#kembalikan">Kembalikan</a> di bawah untuk mengirim kembali ke pengujian.</p>
          </form>
        </section>
      @endcan
    @endif

    {{-- 8. Paraf (Penyelia) --}}
    @if ($p->status === $S::MenungguParaf && ($staff || $lab))
      <section class="panel {{ Gate::allows('paraf', $p) && ! $paraf->contains('lab_id', $u->lab_id) ? 'ring-2 ring-laut-500' : '' }}">
        <div class="panel-head"><h2>Paraf penyelia</h2></div>
        <div class="panel-body space-y-4">
          <ul class="grid gap-2 sm:grid-cols-2">
            @foreach ($labTerlibat as $l)
              @php $pf = $paraf->firstWhere('lab_id', $l->id); @endphp
              <li class="flex items-center gap-3 rounded-lg border border-kabut-200 px-3 py-2.5">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full {{ $pf ? 'bg-lamun-600 text-white' : 'bg-kabut-100 text-kabut-500' }}"><x-ikon :n="$pf ? 'cek' : 'pena'" class="h-4 w-4" /></span>
                <span class="text-sm"><span class="block font-semibold">Lab {{ $l->nama }}</span><span class="text-xs text-kabut-500">{{ $pf ? $pf->user->nama.', '.tanggal($pf->created_at) : 'Belum diparaf' }}</span></span>
              </li>
            @endforeach
          </ul>
          @can('paraf', $p)
            @unless ($paraf->contains('lab_id', $u->lab_id))
              <form method="post" action="{{ route('permohonan.paraf', $p) }}" data-guard>
                @csrf
                <p class="mb-3 text-sm text-kabut-600">Dengan memaraf, Anda menyatakan hasil bidang {{ $u->lab->nama }} pada tabel di bawah sudah benar. Paraf digital mencatat nama, waktu, dan sidik hasil; bila hasil berubah setelahnya, paraf gugur.</p>
                <button class="btn-primary" data-confirm="Paraf hasil Lab {{ $u->lab->nama }} untuk {{ $p->kodeTampil() }}?"><x-ikon n="pena" class="h-4 w-4" />Paraf hasil Lab {{ $u->lab->nama }}</button>
              </form>
            @endunless
          @endcan
        </div>
      </section>
    @endif

    {{-- 9. Pengesahan (Penanggung Jawab) --}}
    @if ($p->status === $S::MenungguPengesahan)
      @can('sahkan', $p)
        <section class="panel ring-2 ring-laut-500">
          <div class="panel-head"><h2>Validasi &amp; pengesahan LHP</h2></div>
          <form method="post" action="{{ route('permohonan.sahkan', $p) }}" class="panel-body space-y-4" data-guard>
            @csrf
            <p class="text-sm text-kabut-600">Seluruh hasil sudah divalidasi Administrator dan diparaf penyelia. Setelah disahkan, sistem memberi nomor LHP urut agenda, kode verifikasi, dan QR Code keaslian.{{ $mandiri ? '' : ' Layanan '.$p->jenis_bayar->label().' lalu menunggu billing pelunasan.' }}</p>
            @if ($parameter->contains(fn ($x) => $x->memenuhi === false))
              <div class="alert-warning"><x-ikon n="info" class="mt-0.5 h-4 w-4 shrink-0" />Ada parameter yang <span class="font-semibold">tidak memenuhi</span> persyaratan mutu.</div>
            @endif
            <button class="btn-ok" data-confirm="Sahkan LHP {{ $p->kodeTampil() }}? Nomor LHP dibuat dan tidak bisa dibatalkan."><x-ikon n="perisai" class="h-4 w-4" />Sahkan LHP</button>
          </form>
        </section>
      @endcan
    @endif

    {{-- Kembalikan (sesuai flowchart):
         validasi hasil -> data belum benar/lengkap -> pengujian (revisi/uji ulang);
         paraf/validasi akhir tidak disetujui -> perbaikan data (ke Administrator) atau pengujian ulang (ke lab). --}}
    @if ($bolehKembali)
      @php
        $pilihanLab = $u->role === $R::Penyelia ? $labTerlibat->where('id', $u->lab_id) : $labTerlibat;
        $pilihan = \App\Services\Workflow::pilihanKembali($p->status);
        $keLab = $p->status === $S::MenungguPemeriksaan;
        $ket = [
            'REVISI' => 'Data belum benar/lengkap; analis memperbaiki input, hasil lama tetap ada',
            'UJI_ULANG' => 'Sampel diuji ulang; hasil lab itu dikosongkan, norma waktu dihitung ulang',
            'PERBAIKAN' => 'Kembali ke validasi hasil oleh Administrator untuk diperbaiki',
        ];
      @endphp
      <details id="kembalikan" class="panel scroll-mt-6" @if($errors->has('catatan') || $errors->has('jenis')) open @endif>
        <summary class="cursor-pointer list-none px-5 py-4 font-bold"><span class="inline-flex items-center gap-2"><x-ikon n="putar" class="h-4 w-4" />{{ $keLab ? 'Kembalikan ke pengujian' : 'Hasil tidak disetujui' }}</span><span class="block text-xs font-normal text-kabut-500">{{ $keLab ? 'Data hasil belum benar atau belum lengkap? Kirim kembali ke lab beserta catatan perbaikan.' : 'Perlu perbaikan data atau pengujian ulang? Paraf yang sudah ada akan diulang.' }}</span></summary>
        <form method="post" action="{{ route('permohonan.kembalikan', $p) }}" class="space-y-4 border-t border-kabut-200 px-5 py-5" data-guard>
          @csrf
          <fieldset class="grid gap-2 sm:grid-cols-2">
            <legend class="label">Tindak lanjut</legend>
            @foreach ($pilihan as $j)
              <label class="choice"><input type="radio" name="jenis" value="{{ $j }}" @if(! $keLab) data-reveal="{{ $j }}" @endif @checked(old('jenis', $pilihan[0]) === $j)><span><span class="block font-semibold">{{ \App\Models\Pengembalian::labelJenis($j) }}</span><span class="text-xs text-kabut-500">{{ $ket[$j] }}</span></span></label>
            @endforeach
          </fieldset>
          @error('jenis')<p class="error">{{ $message }}</p>@enderror
          <div @if(! $keLab) data-reveal-target="UJI_ULANG" @endif>
            <label for="kembali-lab" class="label">Lab yang menguji ulang / memperbaiki</label>
            <select id="kembali-lab" name="lab_id" class="input max-w-sm">@foreach ($pilihanLab as $l)<option value="{{ $l->id }}" @selected((int) old('lab_id') === $l->id)>Lab {{ $l->nama }}</option>@endforeach</select>
            @error('lab_id')<p class="error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label for="kembali-catatan" class="label">Catatan perbaikan</label>
            <textarea id="kembali-catatan" name="catatan" rows="3" maxlength="1000" class="input" placeholder="Jelaskan apa yang salah dan harus diperbaiki">{{ old('catatan') }}</textarea>
            @error('catatan')<p class="error">{{ $message }}</p>@enderror
          </div>
          <button class="btn-danger" data-confirm="Kembalikan {{ $p->kodeTampil() }}? Validasi dan paraf yang sudah ada akan diulang.">Kembalikan</button>
        </form>
      </details>
    @endif

    {{-- ===================== LHP ===================== --}}
    @if ($lhpTerbit)
      @php $lhu = $p->lhu; @endphp
      <section class="panel overflow-hidden">
        <div class="bg-laut-700 px-5 py-4 text-white">
          <p class="text-xs font-semibold opacity-80">Laporan Hasil Pengujian</p>
          <p class="mt-0.5 text-lg font-extrabold">{{ $p->status === $S::Selesai ? 'LHP selesai' : 'LHP disahkan, menunggu pelunasan' }}</p>
        </div>
        <div class="panel-body flex flex-wrap items-end justify-between gap-4">
          <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-kabut-500">Nomor LHP</dt><dd class="kode mt-0.5">{{ $lhu->nomor }}</dd></div>
            <div><dt class="text-xs text-kabut-500">Disahkan</dt><dd class="mt-0.5 font-semibold">{{ tanggal($lhu->validated_at) }}</dd></div>
            @if ($u->role->bolehCetakLhp())
              <div><dt class="text-xs text-kabut-500">Kode verifikasi</dt><dd class="kode mt-0.5 tracking-wider">{{ $lhu->kode_verifikasi }}</dd></div>
              <div><dt class="text-xs text-kabut-500">Disahkan oleh</dt><dd class="mt-0.5 font-semibold">{{ $lhu->validator?->nama }}</dd></div>
            @endif
          </dl>
          @can('lihatLhu', $p)
            <span class="flex flex-wrap gap-2">
              @unless ($pengguna)<a href="{{ route('permohonan.lhu', $p) }}" class="btn-ghost" target="_blank" rel="noopener"><x-ikon n="cetak" class="h-4 w-4" />Pratinjau</a>@endunless
              <a href="{{ route('permohonan.lhp-pdf', $p) }}" class="btn-primary" target="_blank" rel="noopener"><x-ikon n="berkas" class="h-4 w-4" />LHP (PDF)</a>
            </span>
          @endcan
        </div>
        @if ($pengguna)
          <p class="border-t border-kabut-200 px-5 py-3 text-sm text-kabut-600">{{ $p->status === $S::Selesai ? 'LHP sudah diterbitkan dan dapat diunduh di atas. Dokumen asli diserahkan sesuai mekanisme penyerahan Balai.' : 'LHP dapat diunduh setelah pembayaran dikonfirmasi.' }} Keaslian LHP bisa dicek siapa pun lewat QR Code atau kode verifikasi yang tercetak.</p>
        @endif
      </section>
    @endif

    {{-- ===================== Hasil per parameter ===================== --}}
    @if ($adaHasil && ! $tahapSaya && (! $pengguna || $p->status === $S::Selesai))
      <section id="hasil" class="panel scroll-mt-6 overflow-hidden">
        <div class="panel-head"><h2>{{ $lab ? 'Hasil lab Anda' : 'Hasil pengujian' }}</h2>@if($parameter->contains(fn ($x) => $x->memenuhi === false))<span class="badge-bad">Ada yang tidak memenuhi</span>@endif</div>
        <div class="overflow-x-auto">
        <table class="tabel tabel-hp">
          <thead><tr><th>Parameter</th><th>Hasil</th><th>Persyaratan</th><th>Metode</th><th>Keterangan</th>@unless($pengguna)<th>Diinput</th>@endunless</tr></thead>
          <tbody>
          @foreach ($perBidang as $bidang => $list)
            <tr class="bg-kabut-50"><td colspan="6" class="text-xs font-bold uppercase tracking-wide text-kabut-500">{{ $bidang }}</td></tr>
            @foreach ($list as $x)
              <tr>
                <td class="min-w-[160px]"><span class="font-semibold">{{ $x->nama }}</span>@unless($x->akreditasi)<span class="text-kabut-500">*</span>@endunless<span class="block text-xs text-kabut-500">×{{ $x->jumlah }}</span></td>
                <td data-label="Hasil"><span>{{ $x->hasil ?? '–' }}@if($x->memenuhi !== null)<span class="mt-1 block w-fit {{ $x->memenuhi ? 'badge-ok' : 'badge-bad' }}">{{ $x->memenuhi ? 'Memenuhi' : 'Tidak memenuhi' }}</span>@endif</span></td>
                <td class="text-sm text-kabut-600" data-label="Persyaratan">{{ $x->persyaratan ?: '–' }}</td>
                <td class="text-sm text-kabut-600" data-label="Metode">{{ $x->metode }}</td>
                <td class="max-w-[200px] text-sm text-kabut-600" data-label="Keterangan">{{ $x->keterangan ?: '–' }}</td>
                @unless($pengguna)<td class="whitespace-nowrap text-xs text-kabut-500" data-label="Diinput">{{ $x->penginput?->nama ?? '–' }}@if($x->diinput_at)<span class="block">{{ tanggal($x->diinput_at) }}</span>@endif</td>@endunless
              </tr>
            @endforeach
          @endforeach
          </tbody>
        </table>
        </div>
      </section>
    @endif

    {{-- ===================== Parameter yang diminta ===================== --}}
    @if ($pengguna || ! $adaHasil)
      <section class="panel overflow-hidden">
        <div class="panel-head"><h2>Parameter uji yang diminta</h2><span class="text-xs text-kabut-500">{{ $parameter->count() }} parameter</span></div>
        <div class="overflow-x-auto">
        <table class="tabel tabel-hp">
          <thead><tr><th>Parameter</th><th>Bidang</th><th>Metode</th><th class="text-right">Jumlah uji</th>@unless($lab)<th class="text-right">Tarif</th>@endunless</tr></thead>
          <tbody>
            @foreach ($parameter as $x)
              <tr>
                <td class="font-semibold">{{ $x->kode ? $x->kode.' · ' : '' }}{{ $x->nama }}</td>
                <td data-label="Bidang">{{ $x->bidang }}</td>
                <td class="text-sm text-kabut-600" data-label="Metode">{{ $x->metode }}</td>
                <td class="num text-right" data-label="Jumlah uji">{{ $x->jumlah }}</td>
                @unless($lab)<td class="num whitespace-nowrap text-right" data-label="Tarif">{{ rupiah($x->subtotal()) }}</td>@endunless
              </tr>
            @endforeach
          </tbody>
        </table>
        </div>
      </section>
    @endif

    {{-- ===================== Surat tugas, persetujuan, pengembalian, norma ===================== --}}
    @if (! $pengguna && $p->suratTugas->isNotEmpty())
      @php $st = $p->suratTugas; @endphp
      <section class="panel overflow-hidden">
        <div class="panel-head"><h2>Surat tugas pengujian</h2>@can('cetakSuratTugas', $p)<a href="{{ route('permohonan.surat-tugas', $p) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm"><x-ikon n="berkas" class="h-4 w-4" />Surat tugas (PDF)</a>@endcan</div>
        <div class="panel-body text-sm">
          <p>No. FPPC <span class="num font-semibold">{{ $st->first()->nomor }}</span> <span class="text-kabut-500">· {{ tanggal($st->first()->created_at) }}</span></p>
          <ul class="mt-2 space-y-1">
            @foreach ($st as $s)
              <li><span class="font-semibold">{{ $s->penyelia?->nama ?? 'Penyelia '.$s->lab->nama }}</span> <span class="text-kabut-500">· Penyelia {{ $s->lab->nama }}</span></li>
            @endforeach
          </ul>
          @if ($st->first()->catatan)<p class="mt-2 text-kabut-600">Catatan: {{ $st->first()->catatan }}</p>@endif
        </div>
      </section>
    @endif

    @if (! $pengguna && $pengembalian->isNotEmpty())
      <section class="panel overflow-hidden">
        <div class="panel-head"><h2>Riwayat pengembalian ke lab</h2></div>
        <ul class="divide-y divide-kabut-100">
          @foreach ($pengembalian->reverse() as $k)
            <li class="px-5 py-3 text-sm">
              <span class="flex flex-wrap items-center gap-2"><span class="{{ $k->selesai_at ? 'badge-ok' : 'badge-wait' }}">{{ $k->label() }}{{ $k->selesai_at ? ', selesai' : '' }}</span><span class="font-semibold">{{ $k->lab ? 'Lab '.$k->lab->nama : 'Validasi hasil (Administrator)' }}</span><span class="text-xs text-kabut-500">oleh {{ $k->pengirim->nama }}, {{ tanggal($k->created_at) }}</span></span>
              <p class="mt-1 text-kabut-600">{{ $k->catatan }}</p>
              @if ($k->data_lama)<p class="mt-1 text-xs text-kabut-500">Hasil sebelum uji ulang: {{ collect($k->data_lama)->map(fn ($d) => $d['nama'].' = '.($d['hasil'] ?? '–'))->join('; ') }}</p>@endif
            </li>
          @endforeach
        </ul>
      </section>
    @endif

    @if ($tahap->isNotEmpty())
      <section class="panel overflow-hidden">
        <div class="panel-head"><h2>Tim penanganan &amp; norma waktu</h2></div>
        <div class="overflow-x-auto">
        <table class="tabel tabel-hp">
          <thead><tr><th>Lab</th><th>Analis yang menangani</th><th>Masuk</th><th>Batas norma</th><th>Status</th></tr></thead>
          <tbody>
            @foreach ($tahap as $t)
              @php [$tTeks, $tNada] = $t->norma(); @endphp
              <tr>
                <td class="whitespace-nowrap font-semibold">Lab {{ $t->lab->nama }} <span class="font-normal text-kabut-500 sm:block sm:text-xs">· {{ $t->norma_hari }} hari kerja</span></td>
                <td class="min-w-[160px]" data-label="Analis">
                  <span>
                    @forelse ($t->operator as $op)
                      <span class="mb-1 mr-1 inline-flex items-center gap-1.5 rounded-full bg-kabut-100 px-2.5 py-1 text-xs font-semibold"><span class="grid h-5 w-5 place-items-center rounded-full bg-laut-600 text-2xs text-white">{{ $op->initials() }}</span>{{ $op->nama }}</span>
                    @empty
                      <span class="text-xs text-kabut-500">{{ $t->selesai_at ? '–' : 'Belum ada yang menangani' }}</span>
                    @endforelse
                  </span>
                </td>
                <td class="num whitespace-nowrap text-kabut-600" data-label="Masuk">{{ tanggal($t->masuk_at) }}</td>
                <td class="num whitespace-nowrap text-kabut-600" data-label="Batas norma">{{ tanggal($t->batas_at, false) }}</td>
                <td class="whitespace-nowrap" data-label="Status"><span><span class="badge-{{ $tNada }}">{{ $t->selesai_at ? ($tNada === 'ok' ? 'Selesai, tepat waktu' : 'Selesai, '.mb_strtolower($tTeks)) : $tTeks }}</span>@if($t->selesai_at)<span class="mt-1 block text-xs text-kabut-500">{{ tanggal($t->selesai_at) }}</span>@endif</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
        </div>
        <p class="border-t border-kabut-100 px-5 py-3 text-xs text-kabut-500">Hari kerja: Senin–Jumat di luar hari libur nasional. Batas = akhir hari kerja terakhir.</p>
      </section>
    @endif

    {{-- ===================== Dokumen ===================== --}}
    @if ($dokumen->isNotEmpty())
      <section class="panel">
        <div class="panel-head"><h2>Dokumen</h2></div>
        <ul class="divide-y divide-kabut-100">
          @foreach ($dokumen as $d)
            <li class="flex items-center justify-between gap-3 px-5 py-3">
              <span class="flex min-w-0 items-center gap-3"><x-ikon n="berkas" class="h-5 w-5 shrink-0 text-kabut-400" /><span class="min-w-0"><span class="block truncate text-sm font-semibold">{{ $d->original_name }}</span><span class="text-xs text-kabut-500">{{ $d->jenisLabel() }}, {{ ukuran_berkas($d->size) }}, {{ tanggal($d->created_at) }}</span></span></span>
              <a href="{{ route('permohonan.dokumen', [$p, $d]) }}" class="btn-ghost btn-sm shrink-0">Unduh</a>
            </li>
          @endforeach
        </ul>
      </section>
    @endif
  </div>

  {{-- ===================== Kolom samping ===================== --}}
  <aside class="space-y-6">
    <section class="panel">
      <div class="panel-head"><h2>Data sampel</h2></div>
      <dl class="panel-body grid gap-3.5 text-sm">
        @if ($p->kode_sampel)<div><dt class="text-xs text-kabut-500">Kode sampel</dt><dd class="kode mt-0.5">{{ $p->kode_sampel }}</dd>@can('buktiTerima', $p)<a href="{{ route('permohonan.bukti-terima', $p) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm mt-2"><x-ikon n="berkas" class="h-4 w-4" />Bukti terima sampel (PDF)</a>@endcan</div>@endif
        <div><dt class="text-xs text-kabut-500">Nama sampel</dt><dd class="mt-0.5 font-semibold">{{ $p->nama_sampel }}</dd></div>
        <div class="grid grid-cols-2 gap-3">
          <div><dt class="text-xs text-kabut-500">Komoditas</dt><dd class="mt-0.5 font-semibold">{{ $p->jenis_komoditas }}</dd></div>
          <div><dt class="text-xs text-kabut-500">Jumlah</dt><dd class="num mt-0.5 font-semibold">{{ $p->jumlah_sampel }} sampel</dd></div>
        </div>
        @if ($p->bentuk || $p->kemasan || $p->suhu || $p->berat)
          <div><dt class="text-xs text-kabut-500">Kondisi saat diterima</dt><dd class="mt-0.5 font-semibold">{{ collect([$p->bentuk, $p->kemasan, $p->suhu !== null ? $p->suhu.' °C' : null, $p->berat])->filter()->join(' · ') }}</dd></div>
        @endif
        @unless ($lab)
          <div><dt class="text-xs text-kabut-500">Asal</dt><dd class="mt-0.5 font-semibold">{{ $p->asal_sampel }}</dd></div>
          <div><dt class="text-xs text-kabut-500">Tujuan</dt><dd class="mt-0.5 font-semibold">{{ $p->tujuan->label() }}</dd></div>
          <div><dt class="text-xs text-kabut-500">Jenis layanan</dt><dd class="mt-0.5 font-semibold">{{ $p->jenis_bayar->label() }}<span class="block text-xs font-normal text-kabut-500">{{ $p->jenis_bayar->keterangan() }}</span></dd></div>
        @endunless
        @if ($p->keterangan)<div><dt class="text-xs text-kabut-500">Keterangan</dt><dd class="mt-0.5 whitespace-pre-line">{{ $p->keterangan }}</dd></div>@endif
        <div><dt class="text-xs text-kabut-500">Diajukan</dt><dd class="mt-0.5">{{ tanggal($p->created_at) }}</dd></div>
        @if ($p->diterima_at)<div><dt class="text-xs text-kabut-500">Sampel diterima</dt><dd class="mt-0.5">{{ tanggal($p->diterima_at) }}@if(! $pengguna && $p->penerima) oleh {{ $p->penerima->nama }}@endif</dd></div>@endif
      </dl>
    </section>

    @if ($staff && $p->pemohon)
      <section class="panel">
        <div class="panel-head"><h2>Pelanggan</h2></div>
        <dl class="panel-body grid gap-3 text-sm">
          <div><dt class="text-xs text-kabut-500">Instansi</dt><dd class="mt-0.5 font-semibold">{{ $p->pemohon->instansi ?: '–' }}</dd></div>
          <div><dt class="text-xs text-kabut-500">Alamat</dt><dd class="mt-0.5">{{ $p->alamat ?: ($p->pemohon->alamat ?: '–') }}</dd></div>
          <div><dt class="text-xs text-kabut-500">Penanggung jawab</dt><dd class="mt-0.5 font-semibold">{{ $p->pemohon->nama }}</dd></div>
          <div><dt class="text-xs text-kabut-500">Kontak</dt><dd class="mt-0.5"><a href="mailto:{{ $p->pemohon->email }}">{{ $p->pemohon->email }}</a>@if($p->pemohon->telepon)<span class="num block">{{ $p->pemohon->telepon }}</span>@endif</dd></div>
        </dl>
      </section>
    @endif

    @unless ($lab)
      <section class="panel">
        <div class="panel-head"><h2>Pembayaran</h2></div>
        <dl class="panel-body grid gap-3 text-sm">
          <div><dt class="text-xs text-kabut-500">{{ $p->kode_billing ? 'Tagihan' : 'Perkiraan tagihan' }}</dt><dd class="num mt-0.5 font-bold">{{ rupiah($p->kode_billing ? $p->tagihan : $perkiraanTagihan) }}</dd></div>
          <div><dt class="text-xs text-kabut-500">Kode billing SIMPONI</dt><dd class="num mt-0.5 font-semibold">{{ $p->kode_billing ?: ($mandiri ? 'Terbit setelah sampel diregistrasi' : 'Terbit setelah LHP disahkan') }}</dd>@if($p->kode_billing && ($staff || $pengguna))<a href="{{ route('permohonan.bukti-tagihan', $p) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm mt-2"><x-ikon n="berkas" class="h-4 w-4" />Bukti Tagihan PNBP</a>@endif</div>
          @if ($staff)<div><dt class="text-xs text-kabut-500">NTPN</dt><dd class="num mt-0.5 font-semibold">{{ $p->ntpn ?: '–' }}</dd></div>@endif
          <p class="text-xs text-kabut-500">{{ $p->jenis_bayar->keterangan() }}.</p>
        </dl>
      </section>
    @endunless

    @if (! $pengguna && $p->persetujuan->isNotEmpty())
      <section class="panel">
        <div class="panel-head"><h2>Pemeriksaan &amp; paraf</h2></div>
        <ul class="panel-body space-y-3 text-sm">
          @foreach ($p->persetujuan as $ps)
            <li class="flex items-start gap-2"><x-ikon n="cek" class="mt-0.5 h-4 w-4 shrink-0 text-lamun-600" /><span><span class="font-semibold">{{ $ps->label() }}{{ $ps->lab ? ' (Lab '.$ps->lab->nama.')' : '' }}</span><span class="block text-xs text-kabut-500">{{ $ps->user->nama }}, {{ tanggal($ps->created_at) }}</span>@if($ps->catatan)<span class="block text-xs text-kabut-600">{{ $ps->catatan }}</span>@endif</span></li>
          @endforeach
        </ul>
      </section>
    @endif

    <section class="panel">
      <div class="panel-head"><h2>Riwayat</h2></div>
      <ol class="panel-body relative space-y-5 before:absolute before:bottom-6 before:left-[27px] before:top-6 before:w-px before:bg-kabut-200">
        @foreach ($riwayat->reverse() as $r)
          <li class="relative pl-7">
            <span class="absolute left-0 top-1.5 h-[9px] w-[9px] rounded-full {{ $loop->first ? 'bg-laut-600 ring-4 ring-laut-100' : 'bg-kabut-300' }}"></span>
            <p class="text-sm {{ $loop->first ? 'font-semibold' : '' }}">{{ $r->keterangan }}</p>
            <p class="mt-0.5 text-xs text-kabut-500">{{ tanggal($r->created_at) }}@if(! $lab && ! $pengguna && $r->user) oleh {{ $r->user->nama }}@endif</p>
          </li>
        @endforeach
      </ol>
    </section>
  </aside>
</div>
@endsection
