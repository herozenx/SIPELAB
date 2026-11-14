@extends('layouts.app')
@section('title', 'Ajukan uji')
@section('content')
<a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 text-sm font-semibold no-underline"><x-ikon n="kembali" class="h-4 w-4" />Beranda</a>
<h1 class="mt-3">Ajukan permohonan uji</h1>
<p class="mt-1 max-w-prose text-kabut-600">Setelah dikirim, bawa sampel fisik ke loket penerimaan Balai. Tagihan diterbitkan setelah sampel diverifikasi.</p>

<form method="post" action="{{ route('permohonan.store') }}" enctype="multipart/form-data" class="mt-7 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]" novalidate data-guard>
  @csrf
  <div class="panel">
    <div class="panel-body grid gap-5 sm:grid-cols-2">
      <div class="sm:col-span-2">
        <label for="nama_sampel" class="label">Nama sampel</label>
        <input id="nama_sampel" name="nama_sampel" value="{{ old('nama_sampel') }}" class="input" placeholder="Contoh: Udang vaname beku size 40" required maxlength="120" @error('nama_sampel') aria-invalid="true" @enderror>
        @error('nama_sampel')<p class="error">{{ $message }}</p>@enderror
      </div>
      <div>
        <label for="jenis_komoditas" class="label">Jenis komoditas</label>
        <input id="jenis_komoditas" name="jenis_komoditas" value="{{ old('jenis_komoditas') }}" class="input" placeholder="Contoh: Krustasea" required maxlength="80" list="komoditas" @error('jenis_komoditas') aria-invalid="true" @enderror>
        <datalist id="komoditas"><option value="Ikan segar"><option value="Ikan beku"><option value="Krustasea"><option value="Moluska"><option value="Rumput laut"><option value="Produk olahan"></datalist>
        @error('jenis_komoditas')<p class="error">{{ $message }}</p>@enderror
      </div>
      <div>
        <label for="jumlah_sampel" class="label">Jumlah sampel</label>
        <input id="jumlah_sampel" name="jumlah_sampel" type="number" min="1" max="500" value="{{ old('jumlah_sampel', 1) }}" class="input num" required data-jumlah @error('jumlah_sampel') aria-invalid="true" @enderror>
        @error('jumlah_sampel')<p class="error">{{ $message }}</p>@enderror
      </div>
      <div class="sm:col-span-2">
        <label for="asal_sampel" class="label">Asal sampel</label>
        <input id="asal_sampel" name="asal_sampel" value="{{ old('asal_sampel') }}" class="input" placeholder="Contoh: Tambak Desa Kupang, Sidoarjo" required maxlength="150" @error('asal_sampel') aria-invalid="true" @enderror>
        @error('asal_sampel')<p class="error">{{ $message }}</p>@enderror
      </div>
      <fieldset class="sm:col-span-2">
        <legend class="label">Jenis pengguna jasa</legend>
        <div class="grid gap-2 sm:grid-cols-3">
          @foreach (\App\Enums\JenisBayar::cases() as $jb)
            <label class="choice"><input type="radio" name="jenis_bayar" value="{{ $jb->value }}" required @checked(old('jenis_bayar', $jenisTerakhir) === $jb->value)><span><span class="block font-semibold">{{ $jb->label() }}</span><span class="text-xs text-kabut-500">{{ $jb->keterangan() }}</span></span></label>
          @endforeach
        </div>
        @error('jenis_bayar')<p class="error">{{ $message }}</p>@enderror
        <p class="hint">Admin Lab dapat menyesuaikan saat verifikasi sampel. <a href="{{ route('panduan') }}#jenis">Bedanya apa?</a></p>
      </fieldset>
      <fieldset class="sm:col-span-2">
        <legend class="label">Tujuan distribusi</legend>
        <div class="grid gap-2 sm:grid-cols-2">
          @foreach (\App\Enums\Tujuan::cases() as $t)
            <label class="choice"><input type="radio" name="tujuan" value="{{ $t->value }}" @checked(old('tujuan', 'DOMESTIK') === $t->value)><span><span class="block font-semibold">{{ $t->label() }}</span><span class="text-xs text-kabut-500">{{ ['DOMESTIK' => 'Diedarkan di dalam negeri', 'EKSPOR' => 'Dikirim ke luar negeri'][$t->value] }}</span></span></label>
          @endforeach
        </div>
        @error('tujuan')<p class="error">{{ $message }}</p>@enderror
      </fieldset>
      <div class="sm:col-span-2">
        <label for="keterangan" class="label">Keterangan <span class="font-normal text-kabut-500">(opsional)</span></label>
        <textarea id="keterangan" name="keterangan" rows="3" maxlength="1000" class="input" placeholder="Kondisi sampel, permintaan khusus, dan sebagainya">{{ old('keterangan') }}</textarea>
      </div>
      <div class="sm:col-span-2">
        <label for="dokumen" class="label">Dokumen pendukung <span class="font-normal text-kabut-500">(opsional)</span></label>
        <input id="dokumen" name="dokumen[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-laut-50 file:px-4 file:py-2.5 file:font-semibold file:text-laut-700 hover:file:bg-laut-100" data-max-files="3" data-max-size="5242880">
        <p class="hint">Maksimal 3 berkas PDF, JPG, atau PNG, masing-masing 5 MB. Misalnya surat permohonan atau dokumen asal-usul sampel.</p>
        @error('dokumen')<p class="error">{{ $message }}</p>@enderror
        @error('dokumen.*')<p class="error">{{ $message }}</p>@enderror
      </div>
    </div>
  </div>

  <aside class="lg:sticky lg:top-6 lg:self-start">
    <div class="panel" data-estimasi data-per-sampel="{{ $labs->sum('tarif') }}" data-admin="{{ $biayaAdmin }}">
      <div class="panel-head"><h2>Perkiraan tagihan</h2></div>
      <div class="panel-body text-sm">
        <ul class="space-y-1.5">
          @foreach ($labs as $l)
            <li class="flex justify-between"><span>{{ $l->nama }}</span><span class="num">{{ rupiah($l->tarif) }}</span></li>
          @endforeach
          <li class="flex justify-between border-t border-kabut-200 pt-1.5"><span>× <span data-estimasi-jumlah class="num">1</span> sampel</span><span class="num" data-estimasi-subtotal>{{ rupiah($labs->sum('tarif')) }}</span></li>
          <li class="flex justify-between text-kabut-500"><span>Biaya administrasi</span><span class="num">{{ rupiah($biayaAdmin) }}</span></li>
        </ul>
        <p class="mt-3 flex items-baseline justify-between border-t border-kabut-200 pt-3"><span class="font-semibold">Total</span><span class="num text-xl font-extrabold text-laut-600" data-estimasi-total>{{ rupiah($labs->sum('tarif') + $biayaAdmin) }}</span></p>
        <p class="mt-2 text-xs text-kabut-500">Angka final ditetapkan Admin Lab saat verifikasi sampel.</p>
      </div>
    </div>
    <button class="btn-primary mt-4 w-full py-3">Kirim permohonan</button>
  </aside>
</form>
@endsection
