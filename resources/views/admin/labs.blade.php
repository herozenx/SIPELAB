@extends('layouts.app')
@section('title', 'Lab & tarif')
@section('content')
<h1>Laboratorium, tarif, dan norma waktu</h1>
<p class="mt-1 max-w-prose text-kabut-600">Sampel melewati lab aktif sesuai nomor urut, dari kecil ke besar. Tagihan = jumlah tarif lab aktif × jumlah sampel + biaya administrasi ({{ rupiah($biayaAdmin) }}, diatur di <a href="{{ route('admin.settings') }}">Pengaturan</a>).</p>
<p class="mt-2 max-w-prose text-sm text-kabut-600"><span class="font-semibold">Norma waktu</span> = batas hari kerja tiap lab sejak sampel masuk. Jumlah norma lab aktif saat ini: <span class="num font-semibold">{{ $labs->where('aktif', true)->sum('norma_hari') }} hari kerja</span> (target sampel jadi: {{ \App\Models\Setting::get('norma_total_hari') }} hari kerja).</p>

@php
  // HP: tiap lab jadi kartu 2 kolom dengan label; layar lebar (md+): satu baris seperti tabel.
  $kolom = 'grid grid-cols-2 gap-x-3 gap-y-3 p-4 md:grid-cols-[88px_1fr_160px_120px_80px_104px] md:items-center md:gap-0 md:p-0';
  $sel = 'md:px-4 md:py-3';
  $lbl = 'mb-1 block text-xs font-semibold text-kabut-500 md:sr-only';
@endphp
<section class="panel mt-6 overflow-hidden">
  <div class="{{ $kolom }} hidden border-b border-kabut-200 text-xs font-semibold text-kabut-500 md:grid">
    <div class="{{ $sel }}">Urutan</div><div class="{{ $sel }}">Nama lab</div><div class="{{ $sel }}">Tarif per sampel (Rp)</div><div class="{{ $sel }}">Norma (hari kerja)</div><div class="{{ $sel }}">Aktif</div><div></div>
  </div>
  @foreach ($labs as $l)
    <form method="post" action="{{ route('admin.labs.update', $l) }}" class="{{ $kolom }} border-b border-kabut-100" data-guard>
      @csrf @method('put')
      <div class="{{ $sel }}"><label class="{{ $lbl }}" for="urutan-{{ $l->id }}">Urutan</label><input id="urutan-{{ $l->id }}" name="urutan" type="number" min="1" max="99" value="{{ $l->urutan }}" class="input num"></div>
      <div class="{{ $sel }} max-md:order-first max-md:col-span-2"><label class="{{ $lbl }}" for="nama-{{ $l->id }}">Nama lab</label><input id="nama-{{ $l->id }}" name="nama" value="{{ $l->nama }}" class="input"></div>
      <div class="{{ $sel }}"><label class="{{ $lbl }}" for="tarif-{{ $l->id }}">Tarif per sampel (Rp)</label><input id="tarif-{{ $l->id }}" name="tarif" type="number" min="0" step="1000" value="{{ $l->tarif }}" class="input num"></div>
      <div class="{{ $sel }}"><label class="{{ $lbl }}" for="norma-{{ $l->id }}">Norma (hari kerja)</label><input id="norma-{{ $l->id }}" name="norma_hari" type="number" min="0" max="30" value="{{ $l->norma_hari }}" class="input num"></div>
      <div class="{{ $sel }} flex items-center"><input type="hidden" name="aktif" value="0"><label class="flex items-center gap-2 text-sm font-semibold md:font-normal"><input type="checkbox" name="aktif" value="1" class="h-4 w-4 accent-laut-600" @checked($l->aktif)><span class="md:sr-only">Aktif</span></label></div>
      <div class="{{ $sel }} text-right"><button class="btn-ghost btn-sm max-md:w-full">Simpan</button></div>
    </form>
  @endforeach
  <form method="post" action="{{ route('admin.labs.store') }}" class="{{ $kolom }} bg-kabut-50" data-guard>
    @csrf
    <p class="col-span-2 text-sm font-semibold md:hidden">Tambah lab baru</p>
    <div class="{{ $sel }} max-md:order-2"><label class="{{ $lbl }}" for="urutan-baru">Urutan</label><input id="urutan-baru" name="urutan" type="number" min="1" max="99" value="{{ old('urutan', ($labs->max('urutan') ?? 0) + 1) }}" class="input num"></div>
    <div class="{{ $sel }} max-md:order-1 max-md:col-span-2"><label class="{{ $lbl }}" for="nama-baru">Nama lab</label><input id="nama-baru" name="nama" value="{{ old('nama') }}" class="input" placeholder="Nama lab baru"></div>
    <div class="{{ $sel }} max-md:order-2"><label class="{{ $lbl }}" for="tarif-baru">Tarif per sampel (Rp)</label><input id="tarif-baru" name="tarif" type="number" min="0" step="1000" value="{{ old('tarif', 0) }}" class="input num"></div>
    <div class="{{ $sel }} max-md:order-2"><label class="{{ $lbl }}" for="norma-baru">Norma (hari kerja)</label><input id="norma-baru" name="norma_hari" type="number" min="0" max="30" value="{{ old('norma_hari', 2) }}" class="input num"></div>
    <div class="{{ $sel }} flex items-center max-md:order-2"><input type="hidden" name="aktif" value="0"><label class="flex items-center gap-2 text-sm font-semibold md:font-normal"><input type="checkbox" name="aktif" value="1" checked class="h-4 w-4 accent-laut-600"><span class="md:sr-only">Aktif</span></label></div>
    <div class="{{ $sel }} text-right max-md:order-3"><button class="btn-primary btn-sm max-md:w-full">Tambah</button></div>
  </form>
</section>
@if ($errors->any())<p class="error mt-3">{{ $errors->first() }}</p>@endif
<p class="mt-3 text-xs text-kabut-500">Lab yang masih memegang sampel tidak bisa dinonaktifkan. Lab tidak dihapus agar riwayat hasil uji tetap utuh. Perubahan norma berlaku untuk sampel yang masuk lab setelahnya. Operator tiap lab diatur di menu <a href="{{ route('tim.index') }}">Tim operator</a>.</p>
@endsection
