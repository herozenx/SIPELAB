@extends('layouts.app')
@section('title', 'Sampel internal')
@section('content')
@php
  $rows = array_values((array) old('sampel', [['jenis_sampel' => '', 'berat' => '']])) ?: [['jenis_sampel' => '', 'berat' => '']];
@endphp
<a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 text-sm font-semibold no-underline"><x-ikon n="kembali" class="h-4 w-4" />Beranda</a>
<h1 class="mt-3">Catat sampel internal</h1>
<p class="mt-1 max-w-prose text-kabut-600">Untuk sampel Balai tanpa pengguna jasa: Monitoring, Test Result, dan Uji Banding. Kode sampel langsung terbit dan sampel diteruskan ke Lab {{ $labs->first()?->nama }}. Tanpa tagihan.</p>

<form method="post" action="{{ route('permohonan.internal.store') }}" class="panel mt-7 max-w-3xl" novalidate data-guard>
  @csrf
  <div class="panel-body grid gap-5 sm:grid-cols-2">
    <fieldset class="sm:col-span-2">
      <legend class="label">Layanan</legend>
      <div class="grid gap-2 sm:grid-cols-3">
        @foreach (\App\Enums\JenisBayar::internalCases() as $jb)
          <label class="choice"><input type="radio" name="jenis_bayar" value="{{ $jb->value }}" required @checked(old('jenis_bayar', 'MONITORING') === $jb->value)><span><span class="block font-semibold">{{ $jb->label() }}</span><span class="num text-xs text-kabut-500">SP.{{ $jb->kodeSampel() }}/{{ now()->format('dmy') }}.NN</span></span></label>
        @endforeach
      </div>
      @error('jenis_bayar')<p class="error">{{ $message }}</p>@enderror
    </fieldset>
    <div class="sm:col-span-2">
      <label for="pelanggan" class="label">Pelanggan</label>
      <input id="pelanggan" name="pelanggan" value="{{ old('pelanggan') }}" class="input" placeholder="Contoh: Monitoring Pasar Pabean" required maxlength="150" @error('pelanggan') aria-invalid="true" @enderror>
      @error('pelanggan')<p class="error">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
      <label for="alamat" class="label">Alamat</label>
      <input id="alamat" name="alamat" value="{{ old('alamat') }}" class="input" placeholder="Seperti dicetak di LHP" required maxlength="300" @error('alamat') aria-invalid="true" @enderror>
      @error('alamat')<p class="error">{{ $message }}</p>@enderror
    </div>
    <div><label for="no_surat" class="label">No. surat <span class="font-normal text-kabut-500">(opsional)</span></label><input id="no_surat" name="no_surat" value="{{ old('no_surat') }}" class="input num" maxlength="40"></div>
    <div><label for="cp" class="label">CP <span class="font-normal text-kabut-500">(opsional)</span></label><input id="cp" name="cp" value="{{ old('cp') }}" class="input" maxlength="60"></div>
    <fieldset class="sm:col-span-2">
      <legend class="label">Uji eksternal (subkontrak) <span class="font-normal text-kabut-500">(opsional, kolom EKSTERNAL Buku Sampel)</span></legend>
      <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
        @foreach (\App\Enums\JenisBayar::SUBKONTRAK as $lab)
          <label class="flex items-center gap-2"><input type="checkbox" name="subkontrak[]" value="{{ $lab }}" @checked(in_array($lab, (array) old('subkontrak', []), true))> {{ $lab }}</label>
        @endforeach
      </div>
    </fieldset>

    <div class="sm:col-span-2" data-baris="26">
      <p class="label">Sampel <span class="font-normal text-kabut-500">(satu baris per sampel; dicetak di LHP)</span></p>
      <div class="space-y-2" data-baris-list>
        @foreach ($rows as $i => $r)
          <div class="flex items-center gap-2" data-baris-item>
            <span class="num w-6 shrink-0 text-center text-sm font-bold text-kabut-500" data-baris-no></span>
            <input name="sampel[{{ $i }}][jenis_sampel]" value="{{ $r['jenis_sampel'] ?? '' }}" class="input" placeholder="Jenis sampel, mis. Frozen Octopus" maxlength="150" aria-label="Jenis sampel">
            <input name="sampel[{{ $i }}][berat]" value="{{ $r['berat'] ?? '' }}" class="input num w-32 shrink-0" placeholder="Berat" maxlength="30" aria-label="Berat">
            <button type="button" class="btn-ghost btn-sm shrink-0" data-baris-hapus aria-label="Hapus sampel ini"><x-ikon n="silang" class="h-4 w-4" /></button>
          </div>
        @endforeach
      </div>
      <template>
        <div class="flex items-center gap-2" data-baris-item>
          <span class="num w-6 shrink-0 text-center text-sm font-bold text-kabut-500" data-baris-no></span>
          <input name="sampel[__i__][jenis_sampel]" class="input" placeholder="Jenis sampel" maxlength="150" aria-label="Jenis sampel">
          <input name="sampel[__i__][berat]" class="input num w-32 shrink-0" placeholder="Berat" maxlength="30" aria-label="Berat">
          <button type="button" class="btn-ghost btn-sm shrink-0" data-baris-hapus aria-label="Hapus sampel ini"><x-ikon n="silang" class="h-4 w-4" /></button>
        </div>
      </template>
      <button type="button" class="btn-ghost btn-sm mt-2" data-baris-tambah><x-ikon n="tambah" class="h-4 w-4" />Tambah sampel</button>
      @error('sampel')<p class="error">{{ $message }}</p>@enderror
      @error('sampel.*.jenis_sampel')<p class="error">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
      <label for="keterangan" class="label">Keterangan <span class="font-normal text-kabut-500">(opsional, tidak dicetak)</span></label>
      <textarea id="keterangan" name="keterangan" rows="2" maxlength="1000" class="input">{{ old('keterangan') }}</textarea>
    </div>
  </div>
  <div class="border-t border-kabut-200 px-5 py-4"><button class="btn-primary" data-confirm="Catat sampel dan terbitkan kode sampel?">Catat dan teruskan ke lab</button></div>
</form>
@endsection
