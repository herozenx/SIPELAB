@extends('layouts.guest')
@section('title', 'Daftar pengguna jasa')
@section('content')
<div class="mx-auto max-w-xl px-4 py-12 sm:py-16">
  <h1>Daftar sebagai pengguna jasa</h1>
  <p class="mt-2 text-kabut-600">Untuk perorangan, UMKM, atau perusahaan yang ingin menguji mutu hasil perikanan. Sudah punya akun? <a href="{{ route('login') }}" class="font-semibold">Masuk</a>.</p>

  <div class="mt-6">@include('partials.flash')</div>

  <form method="post" action="{{ url('/daftar') }}" class="grid gap-5 sm:grid-cols-2" novalidate>
    @csrf
    <div class="sm:col-span-2">
      <label for="nama" class="label">Nama penanggung jawab</label>
      <input id="nama" name="nama" value="{{ old('nama') }}" class="input" autocomplete="name" required @error('nama') aria-invalid="true" @enderror>
    </div>
    <div class="sm:col-span-2">
      <label for="instansi" class="label">Nama usaha atau instansi</label>
      <input id="instansi" name="instansi" value="{{ old('instansi') }}" class="input" autocomplete="organization" required @error('instansi') aria-invalid="true" @enderror>
      <p class="hint">Dicetak di LHU sebagai pemohon.</p>
    </div>
    <fieldset class="sm:col-span-2">
      <legend class="label">Jenis pengguna jasa</legend>
      <div class="grid gap-2 sm:grid-cols-2">
        @foreach (\App\Enums\JenisBayar::cases() as $jb)
          <label class="choice"><input type="radio" name="tipe_pengguna" value="{{ $jb->value }}" @checked(old('tipe_pengguna', 'MANDIRI') === $jb->value)><span><span class="block font-semibold">{{ $jb->label() }}</span><span class="text-xs text-kabut-500">{{ $jb->keterangan() }}</span></span></label>
        @endforeach
      </div>
      @error('tipe_pengguna')<p class="error">{{ $message }}</p>@enderror
      <p class="hint">Tidak yakin? Pilih Mandiri. Admin Lab dapat menyesuaikan saat verifikasi sampel. <a href="{{ route('panduan') }}#jenis">Bedanya apa?</a></p>
    </fieldset>
    <div>
      <label for="email" class="label">Email</label>
      <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" autocomplete="email" required @error('email') aria-invalid="true" @enderror>
    </div>
    <div>
      <label for="telepon" class="label">Nomor telepon/WA</label>
      <input id="telepon" name="telepon" type="tel" value="{{ old('telepon') }}" class="input num" autocomplete="tel" placeholder="0812-xxxx-xxxx" required @error('telepon') aria-invalid="true" @enderror>
    </div>
    <div>
      <label for="password" class="label">Kata sandi</label>
      <input id="password" name="password" type="password" class="input" autocomplete="new-password" required @error('password') aria-invalid="true" @enderror>
      <p class="hint">Minimal 8 karakter, berisi huruf besar, huruf kecil, dan angka.</p>
    </div>
    <div>
      <label for="password_confirmation" class="label">Ulangi kata sandi</label>
      <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
    </div>
    <div class="sm:col-span-2"><button class="btn-primary w-full py-3 sm:w-auto">Buat akun</button></div>
  </form>
</div>
@endsection
