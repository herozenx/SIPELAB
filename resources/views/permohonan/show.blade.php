@extends('layouts.app')
@section('title', $p->kode)
@section('poll-id', $p->id)
@section('content')
@php
  $S = \App\Enums\Status::class;
  $R = \App\Enums\Role::class;
  $staff = $u->isStaff();
  $pengguna = $u->role === $R::PenggunaJasa;
  $petugas = $u->role === $R::PetugasLab;
  $bisaInput = $petugas && $p->status === $S::DiLab && $p->current_lab_id === $u->lab_id;
  $tolakBayar = $pengguna && $p->status === $S::MenungguPembayaran ? $p->riwayat->last(fn ($r) => str_starts_with((string) $r->keterangan, 'Bukti pembayaran ditolak')) : null;
  $bukti = $dokumen->where('jenis', \App\Models\Dokumen::BUKTI_BAYAR)->last();
  $eksternal = $p->eksternal();
  $J = \App\Enums\JenisBayar::class;
@endphp

<a href="{{ $petugas ? route('dashboard') : route('permohonan.index') }}" class="no-print inline-flex items-center gap-1 text-sm font-semibold no-underline"><x-ikon n="kembali" class="h-4 w-4" />{{ $petugas ? 'Antrean lab' : 'Daftar permohonan' }}</a>

<div class="mt-3 flex flex-wrap items-start justify-between gap-3">
  <div>
    <h1 class="num">{{ $p->kode }}</h1>
    <p class="mt-1 text-kabut-600">{{ $p->nama_sampel }}, {{ $p->jumlah_sampel }} sampel</p>
  </div>
  <x-status :p="$p" class="mt-1 px-3 py-1.5 text-sm" />
</div>

{{-- Jalur tahapan lab & norma waktu: hanya untuk petugas Balai (pengguna jasa cukup melihat status). --}}
@unless ($pengguna)
<section class="panel mt-6 px-4 py-6 sm:px-6" aria-label="Jalur uji">
  <div class="overflow-x-auto pb-1"><div class="min-w-[480px]"><x-jalur :p="$p" :labs="$labs" /></div></div>
  @if ($p->target_selesai_at)
    @php
      $selesaiUji = in_array($p->status, [$S::MenungguValidasi, $S::MenungguPembayaran, $S::MenungguKonfirmasiBayar, $S::Selesai], true)
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

    @if ($staff && $p->status === $S::Diajukan)
      <section class="panel">
        <div class="panel-head"><h2>Verifikasi sampel fisik</h2></div>
        <form method="post" action="{{ route('permohonan.verifikasi', $p) }}" class="panel-body space-y-5" data-guard>
          @csrf
          <p class="text-sm text-kabut-600">Cocokkan sampel yang diserahkan di loket dengan data permohonan: jenis, jumlah, dan kondisi.</p>
          <fieldset class="grid gap-2 sm:grid-cols-2">
            <legend class="sr-only">Keputusan</legend>
            <label class="choice"><input type="radio" name="keputusan" value="valid" @checked(old('keputusan', 'valid') === 'valid') data-reveal="valid"><span><span class="block font-semibold">Sampel sesuai</span><span class="text-xs text-kabut-500">Terbitkan kode billing SIMPONI</span></span></label>
            <label class="choice"><input type="radio" name="keputusan" value="tolak" @checked(old('keputusan') === 'tolak') data-reveal="tolak"><span><span class="block font-semibold">Tolak permohonan</span><span class="text-xs text-kabut-500">Sampel tidak sesuai atau tidak layak uji</span></span></label>
          </fieldset>
          <div data-reveal-target="valid" class="space-y-4">
            <div>
              <label for="jenis_bayar" class="label">Skema pembayaran</label>
              <select id="jenis_bayar" name="jenis_bayar" class="input max-w-md" data-reveal-select>
                @foreach ($J::cases() as $jb)
                  <option value="{{ $jb->value }}" @selected(old('jenis_bayar', $p->jenis_bayar?->value) === $jb->value)>{{ $jb->label() }}: {{ $jb->keterangan() }}</option>
                @endforeach
              </select>
              <p class="hint">Mengikuti jenis akun pengguna jasa ({{ $p->pemohon?->jenisBayar()->label() }}). Ubah bila perlu.</p>
            </div>
            <div data-reveal-when="MANDIRI">
              <label for="kode_billing" class="label">Kode billing SIMPONI</label>
              <input id="kode_billing" name="kode_billing" value="{{ old('kode_billing') }}" class="input num max-w-xs" inputmode="numeric" maxlength="15" placeholder="15 digit angka" @error('kode_billing') aria-invalid="true" @enderror>
              @error('kode_billing')<p class="error">{{ $message }}</p>@enderror
              <p class="hint">Tagihan yang akan dikirim ke pengguna jasa: <span class="num font-semibold text-tinta">{{ rupiah($perkiraanTagihan) }}</span>.</p>
            </div>
            <div data-reveal-when="EKSTERNAL">
              <p class="rounded-lg bg-laut-50 px-4 py-3 text-sm text-laut-700">Sampel langsung diteruskan ke Lab {{ $labs->first()?->nama }}. Tagihan <span class="num font-semibold">{{ rupiah($perkiraanTagihan) }}</span> dibayar setelah LHU jadi; kode billing diisi saat validasi LHU.</p>
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
        <p class="mt-2 text-sm text-kabut-600">Bawa {{ $p->jumlah_sampel }} sampel {{ $p->nama_sampel }} ke loket penerimaan Balai dan sebutkan kode <span class="kode">{{ $p->kode }}</span>.
          @if ($eksternal)
            Sebagai pengguna jasa <span class="font-semibold">eksternal</span>, sampel langsung diuji setelah diverifikasi. Tagihan dibayar setelah LHU jadi.
          @else
            Kode billing SIMPONI muncul di halaman ini setelah sampel diverifikasi.
          @endif
        </p>
      </div></section>
    @endif

    @if ($pengguna && $p->status === $S::MenungguPembayaran)
      <section class="panel">
        <div class="panel-head"><h2>{{ $eksternal ? 'LHU sudah jadi: lunasi untuk mengambil' : 'Bayar lewat SIMPONI' }}</h2></div>
        <div class="panel-body space-y-5">
          @if ($eksternal && $p->lhu?->nomor)
            <div class="alert-success"><x-ikon n="perisai" class="mt-0.5 h-4 w-4 shrink-0" /><div>LHU nomor <span class="kode">{{ $p->lhu->nomor }}</span> sudah divalidasi. LHU dan hasil uji bisa dilihat dan diunduh setelah pembayaran dikonfirmasi Admin Lab.</div></div>
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
          {{ $eksternal ? 'Setelah terkonfirmasi, LHU langsung bisa diunduh di halaman ini.' : 'Setelah terkonfirmasi, sampel mulai diproses di laboratorium.' }}</p>
      </div></section>
    @endif

    @if ($staff && $p->status === $S::MenungguKonfirmasiBayar)
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
              <label class="choice"><input type="radio" name="keputusan" value="terima" @checked(old('keputusan', 'terima') === 'terima') data-reveal="terima"><span><span class="block font-semibold">Dana sudah masuk</span><span class="text-xs text-kabut-500">{{ $eksternal ? 'LHU dibuka untuk pengguna jasa' : 'Sampel diteruskan ke Lab '.$labs->first()?->nama }}</span></span></label>
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

    @if ($pengguna && in_array($p->status, [$S::DiLab, $S::MenungguValidasi], true))
      {{-- Pengguna jasa hanya melihat bahwa sampel sedang diproses, tanpa tahapan internal lab. --}}
      <section class="panel"><div class="panel-body flex items-start gap-4">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-laut-50 text-laut-600"><x-ikon n="jam" class="h-5 w-5" /></span>
        <div>
          <h2>Sampel sedang diproses</h2>
          <p class="mt-1 text-sm text-kabut-600">Sampel Anda sedang diuji di laboratorium Balai. Halaman ini diperbarui otomatis; Anda akan melihat perubahannya begitu LHU {{ $eksternal ? 'jadi dan tagihan pelunasan terbit' : 'terbit' }}.</p>
          @if ($p->target_selesai_at)
            <p class="mt-3 text-sm">Perkiraan selesai: <span class="font-semibold">{{ tanggal($p->target_selesai_at, false) }}</span> <span class="text-kabut-500">({{ \App\Models\Setting::get('norma_total_hari') }} hari kerja)</span></p>
          @endif
        </div>
      </div></section>
    @elseif ($p->status === $S::DiLab && ! $petugas)
      <section class="panel"><div class="panel-body">
        <h2>Sedang diuji di Lab {{ $p->labSaatIni?->nama }}</h2>
        <p class="mt-2 text-sm text-kabut-600">Sampel diteruskan otomatis ke laboratorium berikutnya setelah petugas menginput hasil.</p>
      </div></section>
    @endif

    @if ($bisaInput)
      <section class="panel ring-2 ring-laut-500">
        <div class="panel-head"><h2>Input hasil uji Lab {{ $u->lab->nama }}</h2></div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-kabut-200 bg-kabut-50 px-5 py-3">
          @if ($sudahMenangani)
            <p class="flex items-center gap-2 text-sm font-semibold text-lamun-700"><x-ikon n="cek" class="h-4 w-4" />Anda tercatat menangani sampel ini.</p>
          @else
            <p class="text-sm text-kabut-600">Klik <span class="font-semibold">Tangani</span> agar nama Anda tercatat sebagai operator sampel ini.</p>
            <form method="post" action="{{ route('permohonan.tangani', $p) }}" data-guard>@csrf<button class="btn-ghost btn-sm"><x-ikon n="tangan" class="h-4 w-4" />Tangani sampel</button></form>
          @endif
        </div>
        <form method="post" action="{{ route('permohonan.hasil', $p) }}" enctype="multipart/form-data" class="panel-body space-y-5" data-guard>
          @csrf
          <fieldset>
            <legend class="label">Hasil</legend>
            <div class="grid gap-2 sm:grid-cols-2">
              <label class="choice has-[:checked]:bg-lamun-50 has-[:checked]:shadow-[inset_0_0_0_2px_theme(colors.lamun.500)]"><input type="radio" name="hasil" value="NEGATIF" @checked(old('hasil') === 'NEGATIF') required><span><span class="block font-bold text-lamun-700">Negatif (−)</span><span class="text-xs text-kabut-500">Tidak ditemukan cemaran/penyakit</span></span></label>
              <label class="choice has-[:checked]:bg-karang-50 has-[:checked]:shadow-[inset_0_0_0_2px_theme(colors.karang.500)]"><input type="radio" name="hasil" value="POSITIF" @checked(old('hasil') === 'POSITIF')><span><span class="block font-bold text-karang-600">Positif (+)</span><span class="text-xs text-kabut-500">Ditemukan cemaran/penyakit</span></span></label>
            </div>
            @error('hasil')<p class="error">{{ $message }}</p>@enderror
          </fieldset>
          <div>
            <label for="catatan_hasil" class="label">Catatan pengujian <span class="font-normal text-kabut-500">(opsional)</span></label>
            <textarea id="catatan_hasil" name="catatan" rows="3" maxlength="2000" class="input" placeholder="Metode, parameter, nilai terukur">{{ old('catatan') }}</textarea>
          </div>
          <div>
            <label for="lampiran" class="label">Lampiran <span class="font-normal text-kabut-500">(opsional)</span></label>
            <input id="lampiran" name="lampiran[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-laut-50 file:px-4 file:py-2.5 file:font-semibold file:text-laut-700" data-max-files="3" data-max-size="5242880">
            <p class="hint">Maksimal 3 berkas (lembar kerja, foto), masing-masing 5 MB. Hanya terlihat oleh lab Anda dan admin.</p>
            @error('lampiran')<p class="error">{{ $message }}</p>@enderror
            @error('lampiran.*')<p class="error">{{ $message }}</p>@enderror
          </div>
          <button class="btn-primary" data-confirm="Simpan hasil? Hasil tidak bisa diubah setelah sampel diteruskan.">Simpan dan teruskan</button>
        </form>
      </section>
    @elseif ($petugas && $p->status === $S::DiLab)
      <div class="alert-warning"><x-ikon n="info" class="mt-0.5 h-4 w-4 shrink-0" />Sampel ini sedang di Lab {{ $p->labSaatIni?->nama }}, bukan di lab Anda.</div>
    @endif

    @if ($staff && $p->status === $S::MenungguValidasi && $p->lhu)
      @php $lulus = $p->lhu->status_izin === \App\Enums\StatusIzin::Lulus; @endphp
      <section class="panel">
        <div class="panel-head"><h2>Validasi LHU</h2></div>
        <div class="panel-body space-y-4">
          <p class="text-sm text-kabut-600">Sistem sudah merekap hasil seluruh lab. Periksa rekap di bawah, lalu terbitkan LHU bernomor.</p>
          <p class="flex flex-wrap items-center gap-2 text-sm">Keputusan sistem: <span class="{{ $lulus ? 'badge-ok' : 'badge-bad' }} text-sm">{{ $p->lhu->status_izin->keputusan($p->tujuan) }}</span></p>
          <form method="post" action="{{ route('permohonan.validasi', $p) }}" class="space-y-4" data-guard>
            @csrf
            @if ($eksternal)
              <div class="rounded-lg bg-surya-50 px-4 py-3 text-sm text-surya-700">Pengguna jasa <span class="font-semibold">eksternal</span>: setelah LHU terbit, pengguna membayar <span class="num font-semibold">{{ rupiah($p->tagihan) }}</span>. LHU baru bisa diunduh setelah pelunasan dikonfirmasi.</div>
              <div>
                <label for="kode_billing_lhu" class="label">Kode billing SIMPONI untuk pelunasan</label>
                <input id="kode_billing_lhu" name="kode_billing" value="{{ old('kode_billing') }}" class="input num max-w-xs" inputmode="numeric" maxlength="15" placeholder="15 digit angka" required @error('kode_billing') aria-invalid="true" @enderror>
                @error('kode_billing')<p class="error">{{ $message }}</p>@enderror
              </div>
            @endif
            <button class="btn-ok" data-confirm="Terbitkan LHU untuk {{ $p->kode }}? Nomor LHU dan kode verifikasi akan dibuat dan tidak bisa dibatalkan."><x-ikon n="perisai" class="h-4 w-4" />{{ $eksternal ? 'Terbitkan LHU & kirim tagihan' : 'Terbitkan LHU' }}</button>
          </form>
        </div>
      </section>
    @endif

    @if ($lhu && $lhu->nomor)
      @php $lulus = $lhu->status_izin === \App\Enums\StatusIzin::Lulus; @endphp
      <section class="panel overflow-hidden">
        <div class="{{ $lulus ? 'bg-lamun-600' : 'bg-karang-600' }} px-5 py-4 text-white">
          <p class="text-xs font-semibold opacity-80">Keputusan LHU</p>
          <p class="mt-0.5 text-lg font-extrabold">{{ $lhu->status_izin->keputusan($p->tujuan) }}</p>
        </div>
        <div class="panel-body flex flex-wrap items-end justify-between gap-4">
          <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-kabut-500">Nomor LHU</dt><dd class="kode mt-0.5">{{ $lhu->nomor }}</dd></div>
            <div><dt class="text-xs text-kabut-500">Kode verifikasi</dt><dd class="kode mt-0.5 tracking-wider">{{ $lhu->kode_verifikasi }}</dd></div>
            <div><dt class="text-xs text-kabut-500">Terbit</dt><dd class="mt-0.5 font-semibold">{{ tanggal($lhu->validated_at) }}</dd></div>
            @if ($staff)<div><dt class="text-xs text-kabut-500">Divalidasi oleh</dt><dd class="mt-0.5 font-semibold">{{ $lhu->validator?->nama }}</dd></div>@endif
          </dl>
          <a href="{{ route('permohonan.lhu', $p) }}" class="btn-primary" target="_blank" rel="noopener"><x-ikon n="cetak" class="h-4 w-4" />Lihat dan cetak LHU</a>
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

    {{-- ===================== Hasil uji ===================== --}}
    @if ($hasil->isNotEmpty() && $tampilHasil)
      <section class="panel overflow-hidden">
        <div class="panel-head"><h2>{{ $petugas ? 'Hasil lab Anda' : 'Hasil pengujian' }}</h2></div>
        @if (! $tampilHasil)
          <p class="panel-body text-sm text-kabut-600">{{ $hasil->count() }} dari {{ $labs->count() }} lab sudah selesai menguji. Nilai hasil ditampilkan setelah LHU tervalidasi.</p>
        @else
          <div class="overflow-x-auto">
          <table class="tabel tabel-hp">
            <thead><tr><th>Laboratorium</th><th>Hasil</th><th>Catatan</th>@unless($pengguna)<th>Petugas</th>@endunless<th class="text-right">Diinput</th></tr></thead>
            <tbody>
              @foreach ($hasil as $h)
                <tr>
                  <td class="font-semibold">Lab {{ $h->lab->nama }}</td>
                  <td data-label="Hasil"><span class="{{ $h->positif() ? 'badge-bad' : 'badge-ok' }}">{{ $h->positif() ? 'Positif' : 'Negatif' }}</span></td>
                  <td class="max-w-[300px] whitespace-pre-line text-kabut-600" data-label="Catatan">{{ $h->catatan ?: '–' }}</td>
                  @unless($pengguna)<td data-label="Petugas">{{ $h->petugas->nama }}</td>@endunless
                  <td class="num whitespace-nowrap text-right text-kabut-500" data-label="Diinput">{{ tanggal($h->created_at) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
          </div>
        @endif
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
        <div><dt class="text-xs text-kabut-500">Nama sampel</dt><dd class="mt-0.5 font-semibold">{{ $p->nama_sampel }}</dd></div>
        <div class="grid grid-cols-2 gap-3">
          <div><dt class="text-xs text-kabut-500">Komoditas</dt><dd class="mt-0.5 font-semibold">{{ $p->jenis_komoditas }}</dd></div>
          <div><dt class="text-xs text-kabut-500">Jumlah</dt><dd class="num mt-0.5 font-semibold">{{ $p->jumlah_sampel }} sampel</dd></div>
        </div>
        <div><dt class="text-xs text-kabut-500">Asal</dt><dd class="mt-0.5 font-semibold">{{ $p->asal_sampel }}</dd></div>
        <div><dt class="text-xs text-kabut-500">Tujuan</dt><dd class="mt-0.5 font-semibold">{{ $p->tujuan->label() }}</dd></div>
        @unless ($petugas)
          <div><dt class="text-xs text-kabut-500">Jenis pengguna jasa</dt><dd class="mt-0.5 font-semibold">{{ $p->jenis_bayar->label() }}<span class="block text-xs font-normal text-kabut-500">{{ $p->jenis_bayar->keterangan() }}</span></dd></div>
        @endunless
        @if ($p->keterangan)<div><dt class="text-xs text-kabut-500">Keterangan</dt><dd class="mt-0.5 whitespace-pre-line">{{ $p->keterangan }}</dd></div>@endif
        <div><dt class="text-xs text-kabut-500">Diajukan</dt><dd class="mt-0.5">{{ tanggal($p->created_at) }}</dd></div>
      </dl>
    </section>

    @if ($tampilPemohon && $p->pemohon)
      <section class="panel">
        <div class="panel-head"><h2>Pemohon</h2></div>
        <dl class="panel-body grid gap-3 text-sm">
          <div><dt class="text-xs text-kabut-500">Instansi</dt><dd class="mt-0.5 font-semibold">{{ $p->pemohon->instansi ?: '–' }}</dd></div>
          <div><dt class="text-xs text-kabut-500">Penanggung jawab</dt><dd class="mt-0.5 font-semibold">{{ $p->pemohon->nama }}</dd></div>
          <div><dt class="text-xs text-kabut-500">Kontak</dt><dd class="mt-0.5"><a href="mailto:{{ $p->pemohon->email }}">{{ $p->pemohon->email }}</a>@if($p->pemohon->telepon)<span class="num block">{{ $p->pemohon->telepon }}</span>@endif</dd></div>
        </dl>
      </section>
    @endif

    @if (! $petugas && $p->tagihan)
      <section class="panel">
        <div class="panel-head"><h2>Pembayaran</h2></div>
        <dl class="panel-body grid gap-3 text-sm">
          <div><dt class="text-xs text-kabut-500">Tagihan</dt><dd class="num mt-0.5 font-bold">{{ rupiah($p->tagihan) }}</dd></div>
          <div><dt class="text-xs text-kabut-500">Kode billing SIMPONI</dt><dd class="num mt-0.5 font-semibold">{{ $p->kode_billing ?: ($eksternal ? 'Terbit setelah LHU jadi' : '–') }}</dd></div>
          <div><dt class="text-xs text-kabut-500">NTPN</dt><dd class="num mt-0.5 font-semibold">{{ $p->ntpn ?: 'Belum dikirim' }}</dd></div>
          @if ($eksternal)<p class="text-xs text-kabut-500">Pengguna jasa eksternal: bayar setelah LHU jadi; LHU diambil setelah lunas.</p>@endif
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
