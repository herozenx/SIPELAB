@extends('layouts.guest')
@section('title', 'Cek Keaslian LHP')
@section('content')
<div class="mx-auto max-w-2xl px-4 py-12 sm:px-6 sm:py-16">
  <h1>Cek Keaslian LHP</h1>
  <p class="mt-2 text-kabut-600">Masukkan kode verifikasi 12 karakter yang tercetak di Laporan Hasil Pengujian (LHP), atau pindai QR Code pada LHP.</p>

  <form action="{{ route('verifikasi') }}" method="get" class="mt-6 flex flex-col gap-3 sm:flex-row">
    <label for="kode" class="sr-only">Kode verifikasi</label>
    <input id="kode" name="kode" value="{{ $kode }}" class="input num uppercase placeholder:normal-case" placeholder="Contoh: 3F9A0C12B7E4" maxlength="12" autocomplete="off" required @if($err) aria-invalid="true" aria-describedby="kode-err" @endif>
    <button class="btn-primary shrink-0"><x-ikon n="perisai" class="h-4 w-4" />Cek LHP</button>
  </form>

  @if ($err)
    <div class="alert-error mt-6" id="kode-err" role="alert"><x-ikon n="silang" class="mt-0.5 h-4 w-4 shrink-0" /><div>{{ $err }}</div></div>
  @endif

  @if ($lhu)
    @php $p = $lhu->permohonan; $lulus = $lhu->status_izin === \App\Enums\StatusIzin::Lulus; @endphp
    <div class="panel mt-8 overflow-hidden">
      <div class="{{ $utuh ? 'bg-lamun-600' : 'bg-karang-600' }} px-5 py-4 text-white">
        <p class="flex items-center gap-2 font-bold"><x-ikon :n="$utuh ? 'perisai' : 'silang'" class="h-5 w-5" />{{ $utuh ? 'LHP asli, diterbitkan oleh Balai' : 'Isi LHP tidak cocok dengan catatan sistem' }}</p>
        @unless ($utuh)<p class="mt-1 text-sm text-karang-50">Data LHP ini telah berubah setelah disahkan. Hubungi Balai sebelum memakai dokumen ini.</p>@endunless
      </div>
      <dl class="grid grid-cols-1 gap-x-6 gap-y-4 px-5 py-5 text-sm sm:grid-cols-2">
        <div><dt class="text-kabut-500">Nomor LHP</dt><dd class="kode mt-0.5">{{ $lhu->nomor }}</dd></div>
        <div><dt class="text-kabut-500">Tanggal disahkan</dt><dd class="mt-0.5 font-semibold">{{ tanggal($lhu->validated_at) }}</dd></div>
        <div><dt class="text-kabut-500">Sampel</dt><dd class="mt-0.5 font-semibold">{{ $p->nama_sampel }} ({{ $p->jenis_komoditas }})@if($p->kode_sampel)<span class="num block text-xs font-normal text-kabut-500">Kode sampel {{ $p->kode_sampel }}</span>@endif</dd></div>
        <div><dt class="text-kabut-500">Jumlah dan tujuan</dt><dd class="mt-0.5 font-semibold">{{ $p->jumlah_sampel }} sampel, {{ $p->tujuan->label() }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-kabut-500">Kesesuaian persyaratan mutu</dt><dd class="mt-1"><span class="{{ $lulus ? 'badge-ok' : 'badge-bad' }} text-sm">{{ $lulus ? 'Seluruh parameter memenuhi persyaratan' : 'Ada parameter yang tidak memenuhi persyaratan' }}</span></dd></div>
      </dl>
      <p class="border-t border-kabut-200 px-5 py-3 text-2xs text-kabut-500">Sidik SHA-256: <span class="num break-all">{{ $lhu->hash }}</span></p>
    </div>
  @endif
</div>
@endsection
