@extends('layouts.app')
@section('title', $p->kode_sampel ?: $p->kode)
@section('poll-id', $p->id)
@section('content')
@php
  $S = \App\Enums\Status::class;
  $R = \App\Enums\Role::class;
  $F = \App\Support\FormatLhp::class;
  $staff = $u->isStaff();
  $bisaVerif = $u->role->bolehVerifikasi(); // Admin, Admin Lab, Superadmin
  $pengguna = $u->role === $R::PenggunaJasa;
  $petugas = $u->role === $R::PetugasLab;
  $adm = $u->role === $R::Administrasi;
  $bisaInput = $bisaIsi; // operator lab ini, atau Admin Lab (kepala tim operator)
  $labIsi = $p->labSaatIni?->nama;
  $bisaCek = \Illuminate\Support\Facades\Gate::allows('cek', $p);
  $bisaWord = \Illuminate\Support\Facades\Gate::allows('unduhLhp', $p);
  $tolakBayar = $pengguna && $p->status === $S::MenungguPembayaran ? $p->riwayat->last(fn ($r) => str_starts_with((string) $r->keterangan, 'Bukti pembayaran ditolak')) : null;
  $bukti = $dokumen->where('jenis', \App\Models\Dokumen::BUKTI_BAYAR)->last();
  $eksternal = $p->eksternal();
  $J = \App\Enums\JenisBayar::class;
  $adaIsian = $p->sampel->contains(fn ($s) => $s->hasil->isNotEmpty());
  $pelanggan = $p->pelanggan ?: ($p->pemohon?->instansi ?: $p->pemohon?->nama);
@endphp

<a href="{{ $petugas || $adm ? route('dashboard') : route('permohonan.index') }}" class="no-print inline-flex items-center gap-1 text-sm font-semibold no-underline"><x-ikon n="kembali" class="h-4 w-4" />{{ $petugas ? 'Antrean lab' : ($adm ? 'Antrean pemeriksaan' : 'Daftar permohonan') }}</a>

<div class="mt-3 flex flex-wrap items-start justify-between gap-3">
  <div>
    <h1 class="num">{{ $p->kode_sampel ?: $p->kode }}</h1>
    <p class="mt-1 text-kabut-600">{{ $p->nama_sampel }}, {{ $p->jumlah_sampel }} sampel @if($p->kode_sampel)<span class="num text-kabut-500">· {{ $p->kode }}</span>@endif</p>
  </div>
  <x-status :p="$p" class="mt-1 px-3 py-1.5 text-sm" />
</div>

{{-- Jalur tahapan lab & norma waktu: hanya untuk petugas Balai (pengguna jasa cukup melihat status). --}}
@unless ($pengguna)
<section class="panel mt-6 px-4 py-6 sm:px-6" aria-label="Jalur uji">
  <div class="overflow-x-auto pb-1"><div class="min-w-[560px]"><x-jalur :p="$p" :labs="$labs" /></div></div>
  @if ($p->target_selesai_at)
    @php
      $selesaiUji = in_array($p->status, [$S::MenungguCekAdministrasi, $S::MenungguValidasi, $S::MenungguPembayaran, $S::MenungguKonfirmasiBayar, $S::Selesai], true)
        ? ($tahap->max('selesai_at') ?? $p->updated_at) : null;
      [$normaTeks, $normaNada] = \App\Support\HariKerja::status($p->target_selesai_at, $selesaiUji);
    @endphp
    <p class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-kabut-200 pt-4 text-sm">
      <x-ikon n="jam" class="h-4 w-4 text-kabut-500" />
      <span>Norma waktu sampel jadi <span class="font-semibold">{{ \App\Models\Setting::get('norma_total_hari') }} hari kerja</span>: target <span class="font-semibold">{{ tanggal($p->target_selesai_at, false) }}</span></span>
      <span class="badge-{{ $normaNada }}">{{ $normaTeks }}</span>
    </p>
  @endif
</section>
@endunless

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
  <div class="min-w-0 space-y-6">

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

    @if ($bisaVerif && $p->status === $S::Diajukan)
      <section class="panel">
        <div class="panel-head"><h2>Verifikasi sampel fisik</h2></div>
        <form method="post" action="{{ route('permohonan.verifikasi', $p) }}" class="panel-body space-y-5" data-guard>
          @csrf
          <p class="text-sm text-kabut-600">Cocokkan sampel yang diserahkan di loket dengan data permohonan, lalu catat rinciannya. Data penerimaan di bawah dicatat di Buku Sampel dan LHP.</p>
          <fieldset class="grid gap-2 sm:grid-cols-2">
            <legend class="sr-only">Keputusan</legend>
            <label class="choice"><input type="radio" name="keputusan" value="valid" @checked(old('keputusan', 'valid') === 'valid') data-reveal="valid"><span><span class="block font-semibold">Sampel sesuai</span><span class="text-xs text-kabut-500">Kode sampel terbit</span></span></label>
            <label class="choice"><input type="radio" name="keputusan" value="tolak" @checked(old('keputusan') === 'tolak') data-reveal="tolak"><span><span class="block font-semibold">Tolak permohonan</span><span class="text-xs text-kabut-500">Sampel tidak sesuai atau tidak layak uji</span></span></label>
          </fieldset>
          <div data-reveal-target="valid" class="space-y-5">
            @php
              $baris = array_values((array) old('sampel', $p->sampel->map(fn ($s) => ['jenis_sampel' => $s->jenis_sampel, 'berat' => $s->berat])->all())) ?: [['jenis_sampel' => '', 'berat' => '']];
            @endphp
            <div data-baris="26">
              <p class="label">Rincian sampel <span class="font-normal text-kabut-500">(satu baris per sampel; dicetak di LHP)</span></p>
              <p class="hint !mt-0 mb-2">Produk yang diajukan: <span class="font-semibold text-tinta">{{ $p->nama_sampel }}</span>, {{ $p->jumlah_sampel }} sampel. Bila sampel berbeda jenis (mis. Air dan Es Batu), tulis di baris terpisah: kode sampel diberi huruf A, B, ... dan masing-masing punya halaman LHP sendiri.</p>
              <div class="space-y-2" data-baris-list>
                @foreach ($baris as $i => $r)
                  <div class="flex items-center gap-2" data-baris-item>
                    <span class="num w-6 shrink-0 text-center text-sm font-bold text-kabut-500" data-baris-no></span>
                    <input name="sampel[{{ $i }}][jenis_sampel]" value="{{ $r['jenis_sampel'] ?? '' }}" class="input" maxlength="150" placeholder="Jenis sampel" aria-label="Jenis sampel">
                    <input name="sampel[{{ $i }}][berat]" value="{{ $r['berat'] ?? '' }}" class="input num w-32 shrink-0" maxlength="30" placeholder="Berat" aria-label="Berat">
                    <button type="button" class="btn-ghost btn-sm shrink-0" data-baris-hapus aria-label="Hapus sampel ini"><x-ikon n="silang" class="h-4 w-4" /></button>
                  </div>
                @endforeach
              </div>
              <template>
                <div class="flex items-center gap-2" data-baris-item>
                  <span class="num w-6 shrink-0 text-center text-sm font-bold text-kabut-500" data-baris-no></span>
                  <input name="sampel[__i__][jenis_sampel]" class="input" maxlength="150" placeholder="Jenis sampel, mis. Es Batu" aria-label="Jenis sampel">
                  <input name="sampel[__i__][berat]" class="input num w-32 shrink-0" maxlength="30" placeholder="Berat" aria-label="Berat">
                  <button type="button" class="btn-ghost btn-sm shrink-0" data-baris-hapus aria-label="Hapus sampel ini"><x-ikon n="silang" class="h-4 w-4" /></button>
                </div>
              </template>
              <button type="button" class="btn-ghost btn-sm mt-2" data-baris-tambah><x-ikon n="tambah" class="h-4 w-4" />Tambah sampel</button>
              @error('sampel')<p class="error">{{ $message }}</p>@enderror
              @error('sampel.*.jenis_sampel')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4">
              <div><label for="v_pelanggan" class="label">Pelanggan <span class="font-normal text-kabut-500">(dicetak di LHP)</span></label><input id="v_pelanggan" name="pelanggan" value="{{ old('pelanggan', $p->pelanggan) }}" class="input" maxlength="150" required>@error('pelanggan')<p class="error">{{ $message }}</p>@enderror</div>
              <div><label for="v_alamat" class="label">Alamat <span class="font-normal text-kabut-500">(dicetak di LHP)</span></label><input id="v_alamat" name="alamat" value="{{ old('alamat', $p->alamat) }}" class="input" maxlength="300" required>@error('alamat')<p class="error">{{ $message }}</p>@enderror</div>
            </div>
            <fieldset>
              <legend class="label">Uji eksternal (subkontrak) <span class="font-normal text-kabut-500">(opsional, kolom EKSTERNAL Buku Sampel)</span></legend>
              <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                @foreach (\App\Enums\JenisBayar::SUBKONTRAK as $lab)
                  <label class="flex items-center gap-2"><input type="checkbox" name="subkontrak[]" value="{{ $lab }}" @checked(in_array($lab, (array) old('subkontrak', array_filter(array_map('trim', explode(',', (string) $p->subkontrak)))), true))> {{ $lab }}</label>
                @endforeach
              </div>
            </fieldset>
            <div class="grid gap-4 sm:grid-cols-2">
              <div><label for="no_surat" class="label">No. surat <span class="font-normal text-kabut-500">(opsional)</span></label><input id="no_surat" name="no_surat" value="{{ old('no_surat', $p->no_surat) }}" class="input num" maxlength="40"></div>
              <div><label for="cp" class="label">CP <span class="font-normal text-kabut-500">(opsional)</span></label><input id="cp" name="cp" value="{{ old('cp', $p->cp) }}" class="input" maxlength="60"></div>
            </div>
            <div>
              <label for="jenis_bayar" class="label">Layanan</label>
              <select id="jenis_bayar" name="jenis_bayar" class="input max-w-md" data-reveal-select>
                @foreach ($J::publik() as $jb)
                  <option value="{{ $jb->value }}" @selected(old('jenis_bayar', $p->jenis_bayar?->value) === $jb->value)>{{ $jb->label() }} (SP.{{ $jb->kodeSampel() }}): {{ $jb->keterangan() }}</option>
                @endforeach
              </select>
              <p class="hint">Sesuai pilihan pengguna jasa saat mengajukan. Ubah bila perlu; kode sampel mengikuti layanan.</p>
            </div>
            <div data-reveal-when="MANDIRI SURVEILEN">
              <label for="kode_billing" class="label">Kode billing SIMPONI</label>
              <input id="kode_billing" name="kode_billing" value="{{ old('kode_billing') }}" class="input num max-w-xs" inputmode="numeric" maxlength="15" placeholder="15 digit angka" @error('kode_billing') aria-invalid="true" @enderror>
              @error('kode_billing')<p class="error">{{ $message }}</p>@enderror
              <p class="hint">Tagihan yang akan dikirim ke pengguna jasa: <span class="num font-semibold text-tinta">{{ rupiah($perkiraanTagihan) }}</span>.</p>
            </div>
            <div data-reveal-when="EKSTERNAL">
              <p class="rounded-lg bg-laut-50 px-4 py-3 text-sm text-laut-700">Sampel langsung diteruskan ke Lab {{ $labs->first()?->nama }}. Tagihan <span class="num font-semibold">{{ rupiah($perkiraanTagihan) }}</span> dibayar setelah LHP jadi; kode billing diisi saat menerbitkan LHP.</p>
            </div>
          </div>
          <div data-reveal-target="tolak" hidden>
            <label for="alasan" class="label">Alasan penolakan</label>
            <textarea id="alasan" name="alasan" rows="3" maxlength="500" class="input" placeholder="Jelaskan agar pengguna jasa tahu apa yang harus diperbaiki">{{ old('alasan') }}</textarea>
            @error('alasan')<p class="error">{{ $message }}</p>@enderror
          </div>
          <button class="btn-primary" data-confirm="Simpan keputusan verifikasi untuk {{ $p->kode }}?">Simpan keputusan</button>
        </form>
      </section>
    @endif

    @if ($pengguna && $p->status === $S::Diajukan)
      <section class="panel"><div class="panel-body">
        <h2>Serahkan sampel ke loket</h2>
        <p class="mt-2 text-sm text-kabut-600">Bawa {{ $p->jumlah_sampel }} sampel ({{ $p->nama_sampel }}) ke loket penerimaan Balai dan sebutkan kode <span class="kode">{{ $p->kode }}</span>.
          @if ($eksternal)
            Sebagai pengguna jasa <span class="font-semibold">eksternal</span>, sampel langsung diuji setelah diverifikasi. Tagihan dibayar setelah LHP jadi.
          @else
            Kode billing SIMPONI muncul di halaman ini setelah sampel diverifikasi.
          @endif
        </p>
      </div></section>
    @endif

    @if ($pengguna && $p->status === $S::MenungguPembayaran)
      <section class="panel">
        <div class="panel-head"><h2>{{ $eksternal ? 'LHP sudah jadi: lunasi untuk mengambil' : 'Bayar lewat SIMPONI' }}</h2></div>
        <div class="panel-body space-y-5">
          @if ($eksternal && $p->lhu?->nomor)
            <div class="alert-success"><x-ikon n="perisai" class="mt-0.5 h-4 w-4 shrink-0" /><div>LHP nomor <span class="kode">{{ $p->lhu->nomor }}</span> sudah terbit. LHP dan hasil uji bisa dilihat dan diunduh setelah pembayaran dikonfirmasi Admin Lab.</div></div>
          @endif
          @if ($tolakBayar)
            <div class="alert-error"><x-ikon n="silang" class="mt-0.5 h-4 w-4 shrink-0" /><div>{{ $tolakBayar->keterangan }}. Unggah ulang bukti yang benar.</div></div>
          @endif
          <dl class="grid gap-4 rounded-lg bg-laut-50 p-4 sm:grid-cols-2">
            <div><dt class="text-xs font-semibold text-laut-600">Kode billing</dt><dd class="num mt-0.5 text-lg font-extrabold tracking-wide" data-copy>{{ $p->kode_billing }}</dd></div>
            <div><dt class="text-xs font-semibold text-laut-600">Jumlah setor</dt><dd class="num mt-0.5 text-lg font-extrabold">{{ rupiah($p->tagihan) }}</dd></div>
          </dl>
          <p class="text-sm text-kabut-600">Setor melalui teller bank, ATM, internet/mobile banking, atau kantor pos yang melayani SIMPONI. Setelah berhasil, salin NTPN dari bukti setor.</p>
          <form method="post" action="{{ route('permohonan.bayar', $p) }}" enctype="multipart/form-data" class="grid gap-5 sm:grid-cols-2" data-guard>
            @csrf
            <div>
              <label for="ntpn" class="label">NTPN</label>
              <input id="ntpn" name="ntpn" value="{{ old('ntpn') }}" class="input num uppercase placeholder:normal-case" maxlength="24" placeholder="16 huruf/angka" required @error('ntpn') aria-invalid="true" @enderror>
              @error('ntpn')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
              <label for="bukti" class="label">Bukti setor</label>
              <input id="bukti" name="bukti" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-laut-50 file:px-4 file:py-2.5 file:font-semibold file:text-laut-700" data-max-files="1" data-max-size="5242880">
              @error('bukti')<p class="error">{{ $message }}</p>@enderror
              <p class="hint">PDF, JPG, atau PNG, maksimal 5 MB.</p>
            </div>
            <div class="sm:col-span-2"><button class="btn-primary" data-confirm="Kirim NTPN dan bukti setor? Pastikan NTPN sudah benar."><x-ikon n="unggah" class="h-4 w-4" />Kirim bukti pembayaran</button></div>
          </form>
        </div>
      </section>
    @endif

    @if ($pengguna && $p->status === $S::MenungguKonfirmasiBayar)
      <section class="panel"><div class="panel-body">
        <h2>Pembayaran sedang dicek</h2>
        <p class="mt-2 text-sm text-kabut-600">Admin Lab mencocokkan NTPN <span class="kode">{{ $p->ntpn }}</span> dengan dana masuk di SIMPONI.
          {{ $eksternal ? 'Setelah terkonfirmasi, LHP langsung bisa diunduh di halaman ini.' : 'Setelah terkonfirmasi, sampel mulai diproses di laboratorium.' }}</p>
      </div></section>
    @endif

    @if ($bisaVerif && $p->status === $S::MenungguKonfirmasiBayar)
      <section class="panel">
        <div class="panel-head"><h2>Konfirmasi pembayaran SIMPONI</h2></div>
        <div class="panel-body space-y-5">
          <dl class="grid gap-4 sm:grid-cols-3">
            <div><dt class="text-xs text-kabut-500">Kode billing</dt><dd class="num mt-0.5 font-bold">{{ $p->kode_billing }}</dd></div>
            <div><dt class="text-xs text-kabut-500">NTPN</dt><dd class="num mt-0.5 font-bold">{{ $p->ntpn }}</dd></div>
            <div><dt class="text-xs text-kabut-500">Jumlah tagihan</dt><dd class="num mt-0.5 font-bold">{{ rupiah($p->tagihan) }}</dd></div>
          </dl>
          @if ($bukti)
            <a href="{{ route('permohonan.dokumen', [$p, $bukti]) }}{{ $bukti->mime !== 'application/pdf' ? '?lihat=1' : '' }}" target="_blank" rel="noopener" class="btn-ghost btn-sm"><x-ikon n="berkas" class="h-4 w-4" />Buka bukti setor ({{ ukuran_berkas($bukti->size) }})</a>
          @endif
          <form method="post" action="{{ route('permohonan.konfirmasi', $p) }}" class="space-y-4" data-guard>
            @csrf
            <fieldset class="grid gap-2 sm:grid-cols-2">
              <legend class="sr-only">Keputusan</legend>
              <label class="choice"><input type="radio" name="keputusan" value="terima" @checked(old('keputusan', 'terima') === 'terima') data-reveal="terima"><span><span class="block font-semibold">Dana sudah masuk</span><span class="text-xs text-kabut-500">{{ $eksternal ? 'LHP dibuka untuk pengguna jasa' : 'Sampel diteruskan ke Lab '.$labs->first()?->nama }}</span></span></label>
              <label class="choice"><input type="radio" name="keputusan" value="tolak" @checked(old('keputusan') === 'tolak') data-reveal="tolak"><span><span class="block font-semibold">Bukti tidak valid</span><span class="text-xs text-kabut-500">Minta pengguna mengunggah ulang</span></span></label>
            </fieldset>
            <div data-reveal-target="tolak" hidden>
              <label for="catatan" class="label">Alasan bukti ditolak</label>
              <textarea id="catatan" name="catatan" rows="2" maxlength="500" class="input" placeholder="Contoh: NTPN tidak ditemukan di SIMPONI">{{ old('catatan') }}</textarea>
              @error('catatan')<p class="error">{{ $message }}</p>@enderror
            </div>
            <button class="btn-primary" data-confirm="Simpan keputusan pembayaran untuk {{ $p->kode }}?">Simpan keputusan</button>
          </form>
        </div>
      </section>
    @endif

    @if ($pengguna && $p->status->internalLab())
      {{-- Pengguna jasa hanya melihat bahwa sampel sedang diproses, tanpa tahapan internal Balai. --}}
      <section class="panel"><div class="panel-body flex items-start gap-4">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-laut-50 text-laut-600"><x-ikon n="jam" class="h-5 w-5" /></span>
        <div>
          <h2>Sampel sedang diproses</h2>
          <p class="mt-1 text-sm text-kabut-600">Sampel Anda sedang diuji di laboratorium Balai. Halaman ini diperbarui otomatis; Anda akan melihat perubahannya begitu LHP {{ $eksternal ? 'jadi dan tagihan pelunasan terbit' : 'terbit' }}.</p>
          @if ($p->target_selesai_at)
            <p class="mt-3 text-sm">Perkiraan selesai: <span class="font-semibold">{{ tanggal($p->target_selesai_at, false) }}</span> <span class="text-kabut-500">({{ \App\Models\Setting::get('norma_total_hari') }} hari kerja)</span></p>
          @endif
        </div>
      </div></section>
    @elseif ($p->status === $S::DiLab && ! $petugas && ! $bisaInput)
      <section class="panel"><div class="panel-body">
        <h2>Sedang diuji di Lab {{ $p->labSaatIni?->nama }}</h2>
        @if ($p->dikembalikan)
          <p class="mt-2 text-sm text-kabut-600">Dikembalikan Administrasi untuk diperbaiki: <span class="font-semibold text-tinta">{{ $p->lhu?->catatan_cek }}</span>. Setelah diperbaiki, sampel langsung kembali ke Administrasi.</p>
        @else
          <p class="mt-2 text-sm text-kabut-600">Sampel diteruskan otomatis ke laboratorium berikutnya setelah operator menyimpan isian.</p>
        @endif
      </div></section>
    @endif

    {{-- ===================== Isian operator (centang parameter seperti Buku Sampel) ===================== --}}
    @if ($bisaInput)
      <section class="panel ring-2 ring-laut-500">
        <div class="panel-head"><h2>Isian Lab {{ $labIsi }}</h2>@if($staff)<span class="badge-info">Sebagai kepala tim operator</span>@endif</div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-kabut-200 bg-kabut-50 px-5 py-3">
          @if ($sudahMenangani)
            <p class="flex items-center gap-2 text-sm font-semibold text-lamun-700"><x-ikon n="cek" class="h-4 w-4" />Anda tercatat menangani sampel ini.</p>
          @else
            <p class="text-sm text-kabut-600">Klik <span class="font-semibold">Tangani</span> agar nama Anda tercatat sebagai operator sampel ini.</p>
            <form method="post" action="{{ route('permohonan.tangani', $p) }}" data-guard>@csrf<button class="btn-ghost btn-sm"><x-ikon n="tangan" class="h-4 w-4" />Tangani sampel</button></form>
          @endif
        </div>
        @if ($catatanKembali)
          <div class="alert-warning mx-5 mt-5"><x-ikon n="info" class="mt-0.5 h-4 w-4 shrink-0" /><div><span class="font-semibold">Dikembalikan Administrasi:</span> {{ $catatanKembali }}<span class="mt-1 block text-xs">Isian sebelumnya sudah terisi di bawah. Perbaiki lalu simpan; sampel langsung kembali ke Administrasi.</span></div></div>
        @endif
        @php
          $lama = fn ($sid, $pid) => $isianLama[$sid][$pid] ?? null;
          $adaOld = old('_token') !== null;
        @endphp
        <form method="post" action="{{ route('permohonan.hasil', $p) }}" enctype="multipart/form-data" class="panel-body space-y-6" data-guard>
          @csrf
          @if ($katalog->isEmpty())
            <div class="alert-warning"><x-ikon n="info" class="mt-0.5 h-4 w-4 shrink-0" />Belum ada parameter aktif untuk lab ini. Minta Superadmin mengisi Katalog parameter.</div>
          @endif
          <label class="choice w-full sm:w-auto"><input type="checkbox" name="tanpa_uji" value="1" @checked(old('tanpa_uji')) data-tanpa-uji><span><span class="block font-semibold">Tidak ada uji di lab ini</span><span class="text-xs text-kabut-500">Tidak ada parameter Lab {{ $labIsi }} yang diminta untuk sampel ini</span></span></label>
          @error('isian')<p class="error">{{ $message }}</p>@enderror

          <div class="space-y-6" data-isian>
          <p class="text-sm text-kabut-600">Centang parameter yang diuji, lalu ketik hasilnya. Pangkat ditulis dengan <span class="kode">^</span>, mis. <span class="kode">2,3 x 10^4</span>; satuan otomatis ditambahkan. Persyaratan, metode, dan keterangan terisi otomatis dari katalog.</p>
          @foreach ($p->sampel as $s)
            <fieldset class="rounded-lg border border-kabut-200">
              <legend class="mx-3 px-1.5 text-sm"><span class="kode">{{ $s->kode ?: 'Sampel '.$s->urut }}</span> <span class="font-semibold">{{ $s->jenis_sampel }}</span>@if($s->berat) <span class="text-xs text-kabut-500">· {{ $s->berat }}</span>@endif</legend>
              @foreach ($katalog as $kelompok => $params)
                <div class="px-4 pb-3 pt-2">
                  <p class="mb-2 text-2xs font-bold uppercase tracking-wider text-kabut-500">{{ $kelompok }}</p>
                  <div class="space-y-2">
                  @foreach ($params as $prm)
                    @php
                      $h = $lama($s->id, $prm->id);
                      $key = "isian.{$s->id}.{$prm->id}";
                      $diuji = $adaOld ? (bool) old("$key.diuji") : (bool) $h;
                      $ulangan = $adaOld ? array_values((array) old("$key.ulangan", [])) : ($h?->ulangan ?? array_fill(0, max(1, $prm->ulangan), ''));
                      $ulangan = $ulangan ?: [''];
                      $nm = "isian[{$s->id}][{$prm->id}]";
                      $idb = "p{$s->id}-{$prm->id}";
                    @endphp
                    <div class="rounded-lg border border-kabut-200 has-[[data-param]:checked]:border-laut-400 has-[[data-param]:checked]:bg-laut-50/40" data-param-baris>
                      <label class="flex cursor-pointer items-center gap-3 px-3 py-2.5">
                        <input type="checkbox" name="{{ $nm }}[diuji]" value="1" @checked($diuji) data-param class="h-4 w-4">
                        <span class="text-sm font-semibold">{!! $F::html($prm->nama) !!}</span>
                        <span class="num rounded bg-kabut-100 px-1.5 py-0.5 text-2xs font-bold text-kabut-600">{{ $prm->kode }}</span>
                      </label>
                      @php
                        // Tombol cepat hanya untuk hasil berupa kata (Negatif, ND, ...); angka diketik manual.
                        $kata = array_values(array_filter($prm->pilihan ?? [], fn ($o) => ! preg_match('/^[<>≤≥]?\s*\d/u', $o)));
                        $opsiSyarat = $prm->persyaratan ?? [];
                        $syaratDipilih = $adaOld ? old("$key.persyaratan") : ($h?->persyaratan ?? ($opsiSyarat[0] ?? null));
                      @endphp
                      <div class="space-y-2 border-t border-kabut-200 px-3 py-2.5" data-param-isi @unless($diuji) hidden @endunless>
                        @if ($prm->sub_item)
                          @php $nilaiSub = $adaOld ? (array) old("$key.sub", []) : ($h?->rincian ?? []); @endphp
                          <p class="text-xs text-kabut-500">Isi sub-item yang dinilai saja; yang kosong tidak dicetak.</p>
                          <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($prm->sub_item as $g => $grup)
                              <fieldset class="rounded-lg border border-kabut-200 bg-white px-3 py-1.5">
                                <legend class="px-1 text-xs font-bold">{{ $g + 1 }}. {{ $grup['kelompok'] }}</legend>
                                @foreach ($grup['item'] as $i => $item)
                                  <label class="mt-1 flex items-center justify-between gap-2 text-sm">
                                    <span>{{ $item }}</span>
                                    <input name="{{ $nm }}[sub][{{ $g }}][{{ $i }}]" value="{{ $nilaiSub[$g][$i] ?? '' }}" class="input num w-20 py-1 text-center" maxlength="20" inputmode="decimal" aria-label="{{ $grup['kelompok'] }} {{ $item }}" @unless($diuji) disabled @endunless>
                                  </label>
                                @endforeach
                              </fieldset>
                            @endforeach
                          </div>
                        @else
                          <div class="flex flex-wrap items-center gap-2" data-ulangan>
                            @foreach ($ulangan as $i => $v)
                              <input name="{{ $nm }}[ulangan][]" value="{{ $v }}" class="input num w-36 py-1.5" maxlength="60" placeholder="Hasil" aria-label="Ulangan {{ $i + 1 }} {{ $prm->namaPolos() }}" @unless($diuji) disabled @endunless>
                            @endforeach
                            <button type="button" class="btn-ghost btn-sm" data-ulangan-tambah title="Tambah ulangan">+</button>
                            <button type="button" class="btn-ghost btn-sm" data-ulangan-kurang title="Kurangi ulangan">−</button>
                            @if ($prm->satuan)<span class="text-xs text-kabut-500">{{ $prm->satuan }}</span>@endif
                            @foreach ($kata as $o)<button type="button" class="rounded-full border border-kabut-300 bg-white px-2.5 py-1 text-xs font-semibold text-tinta hover:border-laut-500" data-isi-semua="{{ $o }}" title="Isi semua ulangan">{{ $o }}</button>@endforeach
                          </div>
                        @endif
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                          @if (count($opsiSyarat) > 1)
                            {{-- Persyaratan berbeda per produk (mis. ALT beku vs kaleng): pilih yang sesuai. --}}
                            <label class="flex items-center gap-2 text-kabut-600">Persyaratan
                              <select name="{{ $nm }}[persyaratan]" class="input w-auto py-1 text-sm" @unless($diuji) disabled @endunless>
                                @foreach ($opsiSyarat as $o)<option value="{{ $o }}" @selected($syaratDipilih === $o)>{{ $F::sebaris($o) }}</option>@endforeach
                              </select>
                            </label>
                          @endif
                          <label class="flex items-center gap-2 text-kabut-600"><input type="checkbox" name="{{ $nm }}[melebihi]" value="1" @checked($adaOld ? old("$key.melebihi") : $h?->melebihi) @unless($diuji) disabled @endunless data-melebihi> Melebihi persyaratan</label>
                        </div>
                      </div>
                    </div>
                  @endforeach
                  </div>
                </div>
              @endforeach
            </fieldset>
          @endforeach
          </div>

          <div>
            <label for="catatan_hasil" class="label">Catatan pengujian <span class="font-normal text-kabut-500">(opsional, tidak dicetak)</span></label>
            <textarea id="catatan_hasil" name="catatan" rows="2" maxlength="2000" class="input">{{ old('catatan') }}</textarea>
          </div>
          <div>
            <label for="lampiran" class="label">Lampiran <span class="font-normal text-kabut-500">(opsional)</span></label>
            <input id="lampiran" name="lampiran[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-laut-50 file:px-4 file:py-2.5 file:font-semibold file:text-laut-700" data-max-files="3" data-max-size="5242880">
            <p class="hint">Maksimal 3 berkas (lembar kerja, foto), masing-masing 5 MB. Hanya terlihat oleh lab Anda, Administrasi, dan admin.</p>
            @error('lampiran')<p class="error">{{ $message }}</p>@enderror
            @error('lampiran.*')<p class="error">{{ $message }}</p>@enderror
          </div>
          <button class="btn-primary" data-confirm="Simpan isian dan teruskan {{ $p->dikembalikan ? 'kembali ke Administrasi' : 'ke tahap berikutnya' }}?">Simpan dan teruskan</button>
        </form>
      </section>
    @elseif ($petugas && $p->status === $S::DiLab)
      <div class="alert-warning"><x-ikon n="info" class="mt-0.5 h-4 w-4 shrink-0" />Sampel ini sedang di Lab {{ $p->labSaatIni?->nama }}, bukan di lab Anda.</div>
    @endif

    {{-- ===================== Pemeriksaan Administrasi ===================== --}}
    @if ($p->status === $S::MenungguCekAdministrasi && ($bisaCek || $staff))
      <section class="panel {{ $bisaCek ? 'ring-2 ring-laut-500' : '' }}">
        <div class="panel-head"><h2>Pemeriksaan administrasi</h2></div>
        <div class="panel-body space-y-5">
          <p class="text-sm text-kabut-600">Periksa semua pilihan operator per sampel (tabel <span class="font-semibold">Isian pengujian</span> di bawah) dan file LHP yang dibuat otomatis dari isian tersebut.</p>
          <div class="flex flex-wrap gap-2">
            <a href="{{ route('permohonan.pratinjau', $p) }}" target="_blank" rel="noopener" class="btn-ghost"><x-ikon n="mata" class="h-4 w-4" />Pratinjau LHP</a>
            <a href="{{ route('permohonan.word', $p) }}" class="btn-ghost"><x-ikon n="berkas" class="h-4 w-4" />Unduh file Word LHP</a>
          </div>
          @if ($bisaCek)
            <form method="post" action="{{ route('permohonan.cek', $p) }}" class="space-y-4" data-guard>
              @csrf
              <fieldset class="grid gap-2 sm:grid-cols-2">
                <legend class="sr-only">Hasil pemeriksaan</legend>
                <label class="choice"><input type="radio" name="keputusan" value="sesuai" @checked(old('keputusan', 'sesuai') === 'sesuai') data-reveal="sesuai"><span><span class="block font-semibold">Sesuai</span><span class="text-xs text-kabut-500">Teruskan ke Admin Lab untuk diterbitkan</span></span></label>
                <label class="choice"><input type="radio" name="keputusan" value="kembalikan" @checked(old('keputusan') === 'kembalikan') data-reveal="kembalikan"><span><span class="block font-semibold">Perlu diperbaiki</span><span class="text-xs text-kabut-500">Kembalikan ke operator lab</span></span></label>
              </fieldset>
              <div data-reveal-target="sesuai">
                <label for="catatan_sesuai" class="label">Catatan <span class="font-normal text-kabut-500">(opsional)</span></label>
                <input id="catatan_sesuai" name="catatan" value="{{ old('catatan') }}" class="input" maxlength="500">
              </div>
              <div data-reveal-target="kembalikan" class="grid gap-4 sm:grid-cols-[220px_1fr]" hidden>
                <div>
                  <label for="lab_id" class="label">Kembalikan ke</label>
                  <select id="lab_id" name="lab_id" class="input">
                    @foreach ($labs as $l)<option value="{{ $l->id }}" @selected((int) old('lab_id') === $l->id)>Lab {{ $l->nama }}</option>@endforeach
                  </select>
                  @error('lab_id')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                  <label for="catatan_kembali" class="label">Yang perlu diperbaiki</label>
                  <textarea id="catatan_kembali" name="catatan" rows="2" maxlength="500" class="input" placeholder="Contoh: hasil ALT sampel B tertulis 10^3, di lembar kerja 10^4">{{ old('catatan') }}</textarea>
                  @error('catatan')<p class="error">{{ $message }}</p>@enderror
                </div>
              </div>
              <button class="btn-primary" data-confirm="Simpan hasil pemeriksaan {{ $p->kode_sampel }}?">Simpan hasil pemeriksaan</button>
            </form>
          @else
            <p class="text-sm text-kabut-500">Menunggu pemeriksaan oleh petugas Administrasi.</p>
          @endif
        </div>
      </section>
    @endif

    {{-- ===================== Penerbitan LHP (Admin Lab) ===================== --}}
    @if ($staff && $p->status === $S::MenungguValidasi && $p->lhu)
      <section class="panel ring-2 ring-laut-500">
        <div class="panel-head"><h2>Terbitkan LHP</h2></div>
        <div class="panel-body space-y-5">
          <p class="text-sm text-kabut-600">
            @if ($p->lhu->pemeriksa)
              Diperiksa Administrasi oleh <span class="font-semibold text-tinta">{{ $p->lhu->pemeriksa->nama }}</span> pada {{ tanggal($p->lhu->cek_at) }}.@if($p->lhu->catatan_cek) Catatan: {{ $p->lhu->catatan_cek }}@endif
            @endif
            Nomor LHP dibuat saat diterbitkan dan tidak bisa dibatalkan.
          </p>
          <div class="flex flex-wrap gap-2">
            <a href="{{ route('permohonan.pratinjau', $p) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm"><x-ikon n="mata" class="h-4 w-4" />Pratinjau LHP</a>
            <a href="{{ route('permohonan.word', $p) }}" class="btn-ghost btn-sm"><x-ikon n="berkas" class="h-4 w-4" />Unduh file Word</a>
          </div>
          <form method="post" action="{{ route('permohonan.validasi', $p) }}" class="space-y-4" data-guard>
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label for="ttd" class="label">Penandatangan</label>
                <select id="ttd" name="ttd" class="input" required>
                  @foreach ($penandatangan as $i => $t)<option value="{{ $i }}" @selected((int) old('ttd', 0) === $i)>{{ $t['nama'] }}</option>@endforeach
                </select>
                @error('ttd')<p class="error">{{ $message }}</p>@enderror
              </div>
              <fieldset>
                <legend class="label">Menandatangani sebagai</legend>
                <div class="grid gap-2">
                  <label class="flex items-center gap-2 text-sm"><input type="radio" name="jabatan" value="PJ" @checked(old('jabatan', 'PJ') === 'PJ')> Penanggung jawab Laboratorium <span class="text-xs text-kabut-500">(Kepala UPT dicoret)</span></label>
                  <label class="flex items-center gap-2 text-sm"><input type="radio" name="jabatan" value="KEPALA" @checked(old('jabatan') === 'KEPALA')> Kepala UPT <span class="text-xs text-kabut-500">(Penanggung jawab dicoret)</span></label>
                </div>
              </fieldset>
            </div>
            @if ($eksternal)
              <div class="rounded-lg bg-surya-50 px-4 py-3 text-sm text-surya-700">Pengguna jasa <span class="font-semibold">eksternal</span>: setelah LHP terbit, pengguna membayar <span class="num font-semibold">{{ rupiah($p->tagihan) }}</span>. LHP baru bisa diunduh setelah pelunasan dikonfirmasi.</div>
              <div>
                <label for="kode_billing_lhu" class="label">Kode billing SIMPONI untuk pelunasan</label>
                <input id="kode_billing_lhu" name="kode_billing" value="{{ old('kode_billing') }}" class="input num max-w-xs" inputmode="numeric" maxlength="15" placeholder="15 digit angka" required @error('kode_billing') aria-invalid="true" @enderror>
                @error('kode_billing')<p class="error">{{ $message }}</p>@enderror
              </div>
            @endif
            <button class="btn-ok" data-confirm="Terbitkan LHP {{ $p->kode_sampel }}? Nomor LHP dan kode verifikasi akan dibuat dan tidak bisa dibatalkan."><x-ikon n="perisai" class="h-4 w-4" />{{ $eksternal ? 'Terbitkan LHP & kirim tagihan' : 'Terbitkan LHP' }}</button>
          </form>
        </div>
      </section>
    @endif

    @if ($lhu && $lhu->nomor)
      <section class="panel">
        <div class="panel-head"><h2>LHP terbit</h2></div>
        <div class="panel-body flex flex-wrap items-end justify-between gap-4">
          <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-kabut-500">Nomor LHP</dt><dd class="kode mt-0.5">{{ $lhu->nomor }}</dd></div>
            <div><dt class="text-xs text-kabut-500">Kode verifikasi</dt><dd class="kode mt-0.5 tracking-wider">{{ $lhu->kode_verifikasi }}</dd></div>
            <div><dt class="text-xs text-kabut-500">Terbit</dt><dd class="mt-0.5 font-semibold">{{ tanggal($lhu->validated_at) }}</dd></div>
            @if ($lhu->ttd_nama)<div><dt class="text-xs text-kabut-500">Ditandatangani</dt><dd class="mt-0.5 font-semibold">{{ $lhu->ttd_nama }}</dd></div>@endif
          </dl>
          <div class="flex flex-wrap gap-2">
            <a href="{{ route('permohonan.lhu', $p) }}" class="btn-ghost" target="_blank" rel="noopener"><x-ikon n="cetak" class="h-4 w-4" />Lihat dan cetak</a>
            @if ($bisaWord)<a href="{{ route('permohonan.word', $p) }}" class="btn-primary"><x-ikon n="berkas" class="h-4 w-4" />Unduh LHP (Word)</a>@endif
          </div>
        </div>
      </section>
    @endif

    {{-- ===================== Norma waktu & operator per lab ===================== --}}
    @if ($tahap->isNotEmpty())
      <section class="panel overflow-hidden">
        <div class="panel-head"><h2>Tim penanganan &amp; norma waktu</h2></div>
        <div class="overflow-x-auto">
        <table class="tabel tabel-hp">
          <thead><tr><th>Lab</th><th>Operator yang menangani</th><th>Masuk</th><th>Batas norma</th><th>Status</th></tr></thead>
          <tbody>
            @foreach ($tahap as $t)
              @php [$tTeks, $tNada] = $t->norma(); @endphp
              <tr>
                <td class="whitespace-nowrap font-semibold">Lab {{ $t->lab->nama }} <span class="font-normal text-kabut-500 sm:block sm:text-xs">· {{ $t->norma_hari }} hari kerja</span></td>
                <td class="min-w-[160px]" data-label="Operator">
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

    {{-- ===================== Isian pengujian per sampel ===================== --}}
    @if ($tampilHasil && $adaIsian && (! $bisaInput || $staff))
      <section class="panel overflow-hidden">
        <div class="panel-head"><h2>{{ $petugas ? 'Isian lab Anda' : 'Isian pengujian' }}</h2></div>
        <x-isian-lhp :p="$p" :lab-id="$petugas ? $u->lab_id : null" :tampil-operator="! $pengguna" />
      </section>
    @endif

    {{-- Data lama (sebelum isian parameter): ringkasan per lab --}}
    @if ($tampilHasil && ! $adaIsian && $hasil->isNotEmpty())
      <section class="panel overflow-hidden">
        <div class="panel-head"><h2>{{ $petugas ? 'Hasil lab Anda' : 'Hasil pengujian' }}</h2></div>
        <div class="overflow-x-auto">
        <table class="tabel tabel-hp">
          <thead><tr><th>Laboratorium</th><th>Hasil</th><th>Catatan</th>@unless($pengguna)<th>Petugas</th>@endunless<th class="text-right">Diinput</th></tr></thead>
          <tbody>
            @foreach ($hasil as $h)
              <tr>
                <td class="font-semibold">Lab {{ $h->lab->nama }}</td>
                <td data-label="Hasil"><span class="{{ $h->positif() ? 'badge-bad' : 'badge-ok' }}">{{ $h->label() }}</span></td>
                <td class="max-w-[300px] whitespace-pre-line text-kabut-600" data-label="Catatan">{{ $h->catatan ?: '–' }}</td>
                @unless($pengguna)<td data-label="Petugas">{{ $h->petugas->nama }}</td>@endunless
                <td class="num whitespace-nowrap text-right text-kabut-500" data-label="Diinput">{{ tanggal($h->created_at) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
        </div>
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
      <div class="panel-body space-y-4 text-sm">
        <ul class="space-y-2">
          @foreach ($p->sampel as $s)
            <li class="rounded-lg bg-kabut-50 px-3 py-2">
              <span class="kode block text-xs">{{ $s->kode ?: 'Belum ada kode' }}</span>
              <span class="font-semibold">{{ $s->jenis_sampel }}</span>@if($s->berat)<span class="text-xs text-kabut-500"> · {{ $s->berat }}</span>@endif
            </li>
          @endforeach
        </ul>
        <dl class="grid gap-3.5">
          @unless ($petugas)
            <div><dt class="text-xs text-kabut-500">Pelanggan</dt><dd class="mt-0.5 font-semibold">{{ $pelanggan }}</dd></div>
            @if ($p->alamat)<div><dt class="text-xs text-kabut-500">Alamat</dt><dd class="mt-0.5">{{ $p->alamat }}</dd></div>@endif
            <div><dt class="text-xs text-kabut-500">Layanan</dt><dd class="mt-0.5 font-semibold">{{ $p->jenis_bayar?->label() }}<span class="block text-xs font-normal text-kabut-500">{{ $p->jenis_bayar?->keterangan() }}</span></dd></div>
          @endunless
          @unless ($p->internal())
            <div class="grid grid-cols-2 gap-3">
              <div><dt class="text-xs text-kabut-500">Komoditas</dt><dd class="mt-0.5 font-semibold">{{ $p->jenis_komoditas }}</dd></div>
              <div><dt class="text-xs text-kabut-500">Tujuan</dt><dd class="mt-0.5 font-semibold">{{ $p->tujuan->label() }}</dd></div>
            </div>
            @unless ($petugas)<div><dt class="text-xs text-kabut-500">Asal</dt><dd class="mt-0.5 font-semibold">{{ $p->asal_sampel }}</dd></div>@endunless
          @endunless
          @if ($p->no_surat && ! $petugas)<div><dt class="text-xs text-kabut-500">No. surat</dt><dd class="num mt-0.5">{{ $p->no_surat }}</dd></div>@endif
          @if ($p->cp && ! $petugas)<div><dt class="text-xs text-kabut-500">CP</dt><dd class="mt-0.5">{{ $p->cp }}</dd></div>@endif
          @if ($p->subkontrak && ! $pengguna)<div><dt class="text-xs text-kabut-500">Uji eksternal (subkontrak)</dt><dd class="mt-0.5 font-semibold">{{ $p->subkontrak }}</dd></div>@endif
          @if ($p->keterangan)<div><dt class="text-xs text-kabut-500">Keterangan</dt><dd class="mt-0.5 whitespace-pre-line">{{ $p->keterangan }}</dd></div>@endif
          <div class="grid grid-cols-2 gap-3">
            <div><dt class="text-xs text-kabut-500">Diajukan</dt><dd class="mt-0.5">{{ tanggal($p->created_at, false) }}</dd></div>
            @if ($p->diterima_at)<div><dt class="text-xs text-kabut-500">Diterima</dt><dd class="mt-0.5">{{ tanggal($p->diterima_at, false) }}</dd></div>@endif
          </div>
        </dl>
      </div>
    </section>

    @if ($tampilPemohon && $p->pemohon && ! $p->internal())
      <section class="panel">
        <div class="panel-head"><h2>Pemohon</h2></div>
        <dl class="panel-body grid gap-3 text-sm">
          <div><dt class="text-xs text-kabut-500">Instansi</dt><dd class="mt-0.5 font-semibold">{{ $p->pemohon->instansi ?: '–' }}</dd></div>
          <div><dt class="text-xs text-kabut-500">Penanggung jawab</dt><dd class="mt-0.5 font-semibold">{{ $p->pemohon->nama }}</dd></div>
          <div><dt class="text-xs text-kabut-500">Kontak</dt><dd class="mt-0.5"><a href="mailto:{{ $p->pemohon->email }}">{{ $p->pemohon->email }}</a>@if($p->pemohon->telepon)<span class="num block">{{ $p->pemohon->telepon }}</span>@endif</dd></div>
        </dl>
      </section>
    @endif

    @if (! $petugas && ! $adm && $p->tagihan)
      <section class="panel">
        <div class="panel-head"><h2>Pembayaran</h2></div>
        <dl class="panel-body grid gap-3 text-sm">
          <div><dt class="text-xs text-kabut-500">Tagihan</dt><dd class="num mt-0.5 font-bold">{{ rupiah($p->tagihan) }}</dd></div>
          <div><dt class="text-xs text-kabut-500">Kode billing SIMPONI</dt><dd class="num mt-0.5 font-semibold">{{ $p->kode_billing ?: ($eksternal ? 'Terbit setelah LHP jadi' : '–') }}</dd></div>
          <div><dt class="text-xs text-kabut-500">NTPN</dt><dd class="num mt-0.5 font-semibold">{{ $p->ntpn ?: 'Belum dikirim' }}</dd></div>
          @if ($eksternal)<p class="text-xs text-kabut-500">Pengguna jasa eksternal: bayar setelah LHP jadi; LHP diambil setelah lunas.</p>@endif
        </dl>
      </section>
    @endif

    <section class="panel">
      <div class="panel-head"><h2>Riwayat</h2></div>
      <ol class="panel-body relative space-y-5 before:absolute before:bottom-6 before:left-[27px] before:top-6 before:w-px before:bg-kabut-200">
        @foreach ($riwayat->reverse() as $r)
          <li class="relative pl-7">
            <span class="absolute left-0 top-1.5 h-[9px] w-[9px] rounded-full {{ $loop->first ? 'bg-laut-600 ring-4 ring-laut-100' : 'bg-kabut-300' }}"></span>
            <p class="text-sm {{ $loop->first ? 'font-semibold' : '' }}">{{ $r->keterangan }}</p>
            <p class="mt-0.5 text-xs text-kabut-500">{{ tanggal($r->created_at) }}@if(!$petugas && $r->user) oleh {{ $r->user->nama }}@endif</p>
          </li>
        @endforeach
      </ol>
    </section>
  </aside>
</div>
@endsection
