@extends('layouts.app')
@section('title', 'Ajukan uji')
@section('content')
<a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 text-sm font-semibold no-underline"><x-ikon n="kembali" class="h-4 w-4" />Beranda</a>
<h1 class="mt-3">Ajukan permohonan uji</h1>
<p class="mt-1 max-w-prose text-kabut-600">Permohonan online diajukan sebagai layanan <span class="font-semibold">Mandiri</span>. Setelah dikirim, bawa sampel fisik ke loket penerimaan Balai. Kode billing SIMPONI diterbitkan setelah sampel diverifikasi.</p>

<form method="post" action="{{ route('permohonan.store') }}" enctype="multipart/form-data" class="mt-7 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]" novalidate data-guard>
  @csrf
  <div class="space-y-6">
    <div class="panel">
      <div class="panel-head"><h2>Data permohonan</h2></div>
      <div class="panel-body grid gap-5 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label for="jenis_komoditas" class="label">Jenis komoditas</label>
          <input id="jenis_komoditas" name="jenis_komoditas" value="{{ old('jenis_komoditas') }}" class="input" placeholder="Contoh: Krustasea" required maxlength="80" list="komoditas" @error('jenis_komoditas') aria-invalid="true" @enderror>
          <datalist id="komoditas"><option value="Ikan segar"><option value="Ikan beku"><option value="Krustasea"><option value="Moluska"><option value="Rumput laut"><option value="Produk olahan"></datalist>
          @error('jenis_komoditas')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
          <label for="asal_sampel" class="label">Asal sampel</label>
          <input id="asal_sampel" name="asal_sampel" value="{{ old('asal_sampel') }}" class="input" placeholder="Contoh: Tambak Desa Kupang, Sidoarjo" required maxlength="150" @error('asal_sampel') aria-invalid="true" @enderror>
          @error('asal_sampel')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
          <label for="alamat" class="label">Alamat pelanggan</label>
          <input id="alamat" name="alamat" value="{{ old('alamat', $alamat) }}" class="input" placeholder="Alamat usaha/instansi, dicetak di LHP" required maxlength="300" @error('alamat') aria-invalid="true" @enderror>
          @error('alamat')<p class="error">{{ $message }}</p>@enderror
        </div>
        <fieldset class="sm:col-span-2">
          <legend class="label">Tujuan distribusi</legend>
          <div class="grid gap-2 sm:grid-cols-2">
            @foreach (\App\Enums\Tujuan::cases() as $t)
              <label class="choice"><input type="radio" name="tujuan" value="{{ $t->value }}" @checked(old('tujuan', 'DOMESTIK') === $t->value)><span><span class="block font-semibold">{{ $t->label() }}</span><span class="text-xs text-kabut-500">{{ ['DOMESTIK' => 'Diedarkan di dalam negeri', 'EKSPOR' => 'Dikirim ke luar negeri'][$t->value] }}</span></span></label>
            @endforeach
          </div>
          @error('tujuan')<p class="error">{{ $message }}</p>@enderror
        </fieldset>
      </div>
    </div>

    @php
      // Indeks asli dipertahankan supaya pesan galat produk.N.* tampil di blok yang benar.
      $isianProduk = collect(old('produk', [[]]))->filter(fn ($v, $k) => is_int($k) && is_array($v))->all() ?: [[]];
    @endphp
    <div class="space-y-6" data-produk-daftar data-maks="{{ $maksProduk }}" data-berikut="{{ max(array_keys($isianProduk)) + 1 }}">
      @error('produk')<p class="error">{{ $message }}</p>@enderror
      @foreach ($isianProduk as $i => $nilai)
        @include('permohonan._produk', ['i' => $i, 'nilai' => $nilai])
      @endforeach
    </div>
    <template data-produk-template>@include('permohonan._produk', ['i' => '__i__', 'nilai' => []])</template>
    <div class="flex flex-wrap items-center gap-3">
      <button type="button" class="btn-ghost" data-produk-tambah><x-ikon n="tambah" class="h-4 w-4" />Tambah produk</button>
      <p class="hint">Satu perusahaan menguji beberapa produk di hari yang sama? Tambahkan di sini. Semua produk memakai satu kode sampel (akhiran A, B, C, ...) dan satu nomor LHP. Maksimal {{ $maksProduk }} produk.</p>
    </div>
    <div class="panel">
      <div class="panel-body grid gap-5">
        <div>
          <label for="keterangan" class="label">Keterangan <span class="font-normal text-kabut-500">(opsional)</span></label>
          <textarea id="keterangan" name="keterangan" rows="3" maxlength="1000" class="input" placeholder="Kondisi sampel, titik uji, permintaan khusus, dan sebagainya">{{ old('keterangan') }}</textarea>
        </div>
        <div>
          <label for="dokumen" class="label">Dokumen pendukung <span class="font-normal text-kabut-500">(opsional)</span></label>
          <input id="dokumen" name="dokumen[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-laut-50 file:px-4 file:py-2.5 file:font-semibold file:text-laut-700 hover:file:bg-laut-100" data-max-files="3" data-max-size="5242880">
          <p class="hint">Maksimal 3 berkas PDF, JPG, atau PNG, masing-masing 5 MB. Misalnya surat permohonan atau dokumen asal-usul sampel.</p>
          @error('dokumen')<p class="error">{{ $message }}</p>@enderror
          @error('dokumen.*')<p class="error">{{ $message }}</p>@enderror
        </div>
      </div>
    </div>
  </div>

  <aside class="lg:sticky lg:top-6 lg:self-start">
    <div class="panel" data-estimasi data-admin="{{ $biayaAdmin }}">
      <div class="panel-head"><h2>Perkiraan tagihan</h2></div>
      <div class="panel-body text-sm">
        <ul class="space-y-1.5">
          <li class="flex justify-between"><span><span data-estimasi-n class="num">0</span> parameter dipilih<span class="block text-xs text-kabut-500"><span data-estimasi-produk class="num">1</span> produk</span></span><span class="num" data-estimasi-subtotal>{{ rupiah(0) }}</span></li>
          <li class="flex justify-between text-kabut-500"><span>Biaya administrasi</span><span class="num">{{ rupiah($biayaAdmin) }}</span></li>
        </ul>
        <p class="mt-3 flex items-baseline justify-between border-t border-kabut-200 pt-3"><span class="font-semibold">Total</span><span class="num text-xl font-extrabold text-laut-600" data-estimasi-total>{{ rupiah($biayaAdmin) }}</span></p>
        <p class="mt-2 text-xs text-kabut-500">Angka final mengikuti billing SIMPONI resmi yang diterbitkan Operator Bendahara.</p>
      </div>
    </div>
    <button class="btn-primary mt-4 w-full py-3">Kirim permohonan</button>
  </aside>
</form>
@endsection
