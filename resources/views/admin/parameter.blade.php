@extends('layouts.app')
@section('title', 'Katalog parameter')
@section('content')
@php
  $F = \App\Support\FormatLhp::class;
  $e = $edit;
  $v = fn ($k, $def = '') => old($k, $e ? (is_array($e->$k) ? implode("\n", $e->$k) : $e->$k) : $def);
@endphp
<h1>Katalog parameter</h1>
<p class="mt-1 max-w-prose text-kabut-600">Parameter yang bisa dicentang operator, dikelompokkan seperti kolom Buku Sampel. Teks persyaratan, metode, dan keterangan dicetak di LHP persis seperti ditulis di sini. Perubahan berlaku untuk isian berikutnya; LHP yang sudah terbit tidak berubah.</p>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
  <div class="space-y-6">
    @foreach ($labs as $lab)
      @php $rows = $grup->get($lab->id, collect()); @endphp
      <section class="panel overflow-hidden">
        <div class="panel-head"><h2>Lab {{ $lab->nama }}</h2>@unless($lab->aktif)<span class="badge-bad">Lab nonaktif</span>@endunless</div>
        @if ($rows->isEmpty())
          <p class="px-5 py-4 text-sm text-kabut-500">Belum ada parameter.</p>
        @else
          <div class="overflow-x-auto">
            <table class="tabel tabel-hp text-sm">
              <thead><tr><th>Kelompok · kode</th><th>Parameter</th><th>Persyaratan</th><th>Metode</th><th>Status</th><th></th></tr></thead>
              <tbody>
                @foreach ($rows as $prm)
                  <tr class="align-top {{ $prm->aktif ? '' : 'opacity-60' }}">
                    <td class="whitespace-nowrap" data-label="Kode"><span class="text-xs text-kabut-500">{{ $prm->kelompok }}</span><span class="num block font-bold">{{ $prm->kode }}</span></td>
                    <td data-label="Parameter"><span class="font-semibold">{!! $F::html($prm->nama) !!}</span><span class="block text-xs text-kabut-500">{{ $prm->bidang }}/{{ $prm->bidang_en }}@if($prm->satuan) · {{ $prm->satuan }}@endif</span></td>
                    <td data-label="Persyaratan">{!! collect($prm->persyaratan ?? [])->map(fn ($s) => $F::html($s))->join('<br>') ?: '<span class="text-karang-600">Belum diisi</span>' !!}</td>
                    <td data-label="Metode">{!! $F::html($prm->metode) ?: '<span class="text-karang-600">Belum diisi</span>' !!}</td>
                    <td data-label="Status"><span class="{{ $prm->aktif ? 'badge-ok' : 'badge-wait' }}">{{ $prm->aktif ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="text-right"><a href="{{ route('admin.parameter', ['edit' => $prm->id]) }}#form" class="btn-ghost btn-sm">Ubah</a></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </section>
    @endforeach
  </div>

  <form id="form" method="post" action="{{ $e ? route('admin.parameter.update', $e) : route('admin.parameter.store') }}" class="panel self-start xl:sticky xl:top-6" novalidate data-guard>
    @csrf @if($e) @method('put') @endif
    <div class="panel-head"><h2>{{ $e ? 'Ubah parameter' : 'Tambah parameter' }}</h2>@if($e)<a href="{{ route('admin.parameter') }}" class="text-sm font-semibold">Batal</a>@endif</div>
    <div class="panel-body space-y-4">
      <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2"><label for="lab_id" class="label">Lab</label>
          <select id="lab_id" name="lab_id" class="input">@foreach ($labs as $l)<option value="{{ $l->id }}" @selected((int) $v('lab_id') === $l->id)>{{ $l->nama }}</option>@endforeach</select></div>
        <div><label for="kelompok" class="label">Kelompok</label><input id="kelompok" name="kelompok" value="{{ $v('kelompok') }}" class="input" placeholder="BAKTERIOLOGI" maxlength="30">@error('kelompok')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label for="kode" class="label">Kode Buku Sampel</label><input id="kode" name="kode" value="{{ $v('kode') }}" class="input num" placeholder="ALT" maxlength="12">@error('kode')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label for="bidang" class="label">Bidang (LHP)</label><input id="bidang" name="bidang" value="{{ $v('bidang') }}" class="input" placeholder="Biologi" maxlength="40" list="bidang-list"></div>
        <div><label for="bidang_en" class="label">Bidang (Inggris)</label><input id="bidang_en" name="bidang_en" value="{{ $v('bidang_en') }}" class="input" placeholder="Biology" maxlength="40"></div>
        <datalist id="bidang-list"><option value="Organoleptik"><option value="Biologi"><option value="Kimia"><option value="Radionuklida"></datalist>
      </div>
      <div><label for="nama" class="label">Nama parameter di LHP</label><input id="nama" name="nama" value="{{ $v('nama') }}" class="input" maxlength="200">@error('nama')<p class="error">{{ $message }}</p>@enderror<p class="hint">Tulis _di antara garis bawah_ untuk huruf miring, mis. _Salmonella_ spp.</p></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label for="satuan" class="label">Satuan angka</label><input id="satuan" name="satuan" value="{{ $v('satuan') }}" class="input" placeholder="kol/g" maxlength="30"></div>
        <div><label for="ulangan" class="label">Ulangan awal</label><input id="ulangan" name="ulangan" type="number" min="1" max="20" value="{{ $v('ulangan', 1) }}" class="input num"></div>
      </div>
      <div><label for="pilihan" class="label">Pilihan hasil cepat</label><textarea id="pilihan" name="pilihan" rows="2" class="input" placeholder="Negatif/25 g&#10;Positif/25 g">{{ $v('pilihan') }}</textarea><p class="hint">Satu per baris. Operator tetap bisa mengetik sendiri.</p></div>
      <div><label for="persyaratan" class="label">Persyaratan mutu</label><textarea id="persyaratan" name="persyaratan" rows="3" class="input" placeholder="Maks 1,0 x 10^5 kol/g">{{ $v('persyaratan') }}</textarea><p class="hint">Satu per baris; baris pertama jadi pilihan awal. Pangkat: 10^5.</p></div>
      <div><label for="metode" class="label">Metode acuan</label><textarea id="metode" name="metode" rows="2" class="input" maxlength="150">{{ $v('metode') }}</textarea><p class="hint">Tambahkan * bila parameter belum terakreditasi (seperti di LHP).</p></div>
      <div><label for="keterangan" class="label">Keterangan</label><textarea id="keterangan" name="keterangan" rows="2" class="input" maxlength="150" placeholder="LoD ... / LoQ ...">{{ $v('keterangan') }}</textarea></div>
      <div class="grid grid-cols-2 items-end gap-3">
        <div><label for="urutan" class="label">Urutan</label><input id="urutan" name="urutan" type="number" min="0" max="999" value="{{ $v('urutan', 0) }}" class="input num"></div>
        <label class="flex items-center gap-2 pb-2.5 text-sm font-semibold"><input type="checkbox" name="aktif" value="1" @checked(old('aktif', $e ? $e->aktif : true))> Aktif</label>
      </div>
    </div>
    <div class="border-t border-kabut-200 px-5 py-4"><button class="btn-primary">Simpan parameter</button></div>
  </form>
</div>
@endsection
