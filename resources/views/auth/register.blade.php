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
