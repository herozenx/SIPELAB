@extends('layouts.app')
@section('title', 'Parameter & tarif')
@section('content')
@php $e = $edit; $belumTarif = $rows->flatten()->where('aktif', true)->where('tarif', 0)->count(); @endphp
<h1>Parameter uji &amp; tarif</h1>
<p class="mt-1 max-w-prose text-kabut-600">Daftar parameter yang bisa dipilih pengguna jasa saat mengajukan permohonan (sesuai FPPCU). Tagihan = tarif tiap parameter × jumlah pengujian + biaya administrasi ({{ rupiah($biayaAdmin) }}). Perubahan berlaku untuk permohonan berikutnya; permohonan lama menyimpan salinan datanya sendiri.</p>
@if ($belumTarif)
  <div class="alert-warning mt-4"><x-ikon n="info" class="mt-0.5 h-4 w-4 shrink-0" /><div>{{ $belumTarif }} parameter aktif belum punya tarif (Rp0). Isi tarif resmi sesuai ketentuan PNBP yang berlaku.</div></div>
@endif

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
  <div class="space-y-6">
    @foreach ($rows as $bidang => $list)
      <section class="panel overflow-hidden">
        <div class="panel-head"><h2>{{ $bidang }}</h2><span class="text-xs text-kabut-500">{{ $list->count() }} parameter</span></div>
        <div class="overflow-x-auto">
        <table class="tabel tabel-hp">
          <thead><tr><th>Kode</th><th>Parameter</th><th>Lab</th><th>Persyaratan bawaan</th><th class="text-right">Tarif</th><th></th></tr></thead>
          <tbody>
          @foreach ($list as $r)
            <tr class="{{ $r->aktif ? '' : 'opacity-60' }}">
              <td class="kode whitespace-nowrap">{{ $r->kode }}</td>
              <td class="min-w-[200px]"><span class="font-semibold">{{ $r->nama }}</span>@unless($r->akreditasi)<span class="ml-1 text-xs text-kabut-500" title="Belum terakreditasi">*</span>@endunless<span class="block text-xs text-kabut-500">{{ $r->metode }}@if($r->satuan) · {{ $r->satuan }}@endif</span>@unless($r->aktif)<span class="badge-bad mt-1">Nonaktif</span>@endunless</td>
              <td class="whitespace-nowrap" data-label="Lab">{{ $r->lab->nama }}</td>
              <td class="text-sm text-kabut-600" data-label="Persyaratan">{{ $r->persyaratan ?: '–' }}</td>
              <td class="num whitespace-nowrap text-right {{ $r->tarif ? '' : 'text-karang-600' }}" data-label="Tarif">{{ rupiah($r->tarif) }}</td>
              <td class="text-right max-sm:!pt-2"><a href="{{ route('admin.parameter', ['edit' => $r->id]) }}" class="btn-ghost btn-sm">Ubah</a></td>
            </tr>
          @endforeach
          </tbody>
        </table>
        </div>
      </section>
    @endforeach
    <p class="text-xs text-kabut-500">* Parameter belum terakreditasi (dicetak dengan tanda * di LHP).</p>
  </div>

  <section class="panel self-start xl:sticky xl:top-6">
    <div class="panel-head"><h2>{{ $e ? 'Ubah parameter' : 'Tambah parameter' }}</h2>@if($e)<a href="{{ route('admin.parameter') }}" class="text-sm font-semibold">Batal</a>@endif</div>
    <form method="post" action="{{ $e ? route('admin.parameter.update', $e) : route('admin.parameter.store') }}" class="panel-body space-y-4" novalidate data-guard>
      @csrf @if($e) @method('put') @endif
      <div class="grid grid-cols-[100px_1fr] gap-3">
        <div><label for="p-kode" class="label">Kode</label><input id="p-kode" name="kode" value="{{ old('kode', $e?->kode) }}" class="input num uppercase" maxlength="10" required>@error('kode')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label for="p-nama" class="label">Nama parameter</label><input id="p-nama" name="nama" value="{{ old('nama', $e?->nama) }}" class="input" required>@error('nama')<p class="error">{{ $message }}</p>@enderror</div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label for="p-bidang" class="label">Bidang di LHP</label><input id="p-bidang" name="bidang" value="{{ old('bidang', $e?->bidang) }}" class="input" list="bidang-list" required><datalist id="bidang-list">@foreach($rows->keys() as $b)<option value="{{ $b }}">@endforeach</datalist></div>
        <div><label for="p-lab" class="label">Diuji di lab</label>
          <select id="p-lab" name="lab_id" class="input">@foreach ($labs as $l)<option value="{{ $l->id }}" @selected((int) old('lab_id', $e?->lab_id) === $l->id)>{{ $l->nama }}</option>@endforeach</select>
        </div>
      </div>
      <div><label for="p-metode" class="label">Metode acuan</label><input id="p-metode" name="metode" value="{{ old('metode', $e?->metode) }}" class="input" required>@error('metode')<p class="error">{{ $message }}</p>@enderror</div>
      <div class="grid grid-cols-[1fr_110px] gap-3">
        <div><label for="p-syarat" class="label">Persyaratan mutu bawaan</label><input id="p-syarat" name="persyaratan" value="{{ old('persyaratan', $e?->persyaratan) }}" class="input" placeholder="mis. Negatif/25 g"></div>
        <div><label for="p-satuan" class="label">Satuan</label><input id="p-satuan" name="satuan" value="{{ old('satuan', $e?->satuan) }}" class="input" placeholder="mg/kg"></div>
      </div>
      <div><label for="p-ket" class="label">Keterangan bawaan di LHP</label><input id="p-ket" name="keterangan" value="{{ old('keterangan', $e?->keterangan) }}" class="input" placeholder="mis. LoD 6,03 mg/kg LoQ 19,91 mg/kg"></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label for="p-tarif" class="label">Tarif per pengujian (Rp)</label><input id="p-tarif" name="tarif" type="number" min="0" step="500" value="{{ old('tarif', $e?->tarif ?? 0) }}" class="input num" required>@error('tarif')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label for="p-urutan" class="label">Urutan</label><input id="p-urutan" name="urutan" type="number" min="0" max="999" value="{{ old('urutan', $e?->urutan ?? 99) }}" class="input num"></div>
      </div>
      <label class="flex items-center gap-2 text-sm font-semibold"><input type="hidden" name="akreditasi" value="0"><input type="checkbox" name="akreditasi" value="1" class="h-4 w-4 accent-laut-600" @checked(old('akreditasi', $e?->akreditasi ?? true))> Ruang lingkup akreditasi</label>
      <label class="flex items-center gap-2 text-sm font-semibold"><input type="hidden" name="aktif" value="0"><input type="checkbox" name="aktif" value="1" class="h-4 w-4 accent-laut-600" @checked(old('aktif', $e?->aktif ?? true))> Aktif (bisa dipilih pengguna jasa)</label>
      <button class="btn-primary w-full">{{ $e ? 'Simpan perubahan' : 'Tambah parameter' }}</button>
    </form>
  </section>
</div>
@endsection
