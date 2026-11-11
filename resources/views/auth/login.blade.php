@extends('layouts.guest')
@section('title', 'Masuk')
@section('content')
<div class="mx-auto max-w-md px-4 py-12 sm:py-16">
  <h1>Masuk ke SIPELAB</h1>
  <p class="mt-2 text-kabut-600">Belum punya akun? <a href="{{ route('register') }}" class="font-semibold">Daftar sebagai pengguna jasa</a>.</p>

  <div class="mt-6">@include('partials.flash')</div>

  <form method="post" action="{{ url('/masuk') }}" class="space-y-5" novalidate>
    @csrf
    <fieldset>
      <legend class="label">Masuk sebagai</legend>
      <div class="grid grid-cols-3 gap-1 rounded-lg bg-kabut-100 p-1 text-sm">
        @foreach (['pengguna_jasa' => 'Pengguna jasa', 'operator' => 'Operator', 'superadmin' => 'Superadmin'] as $v => $l)
          <label class="cursor-pointer rounded-md px-2 py-2 text-center font-semibold text-kabut-600 has-[:checked]:bg-white has-[:checked]:text-laut-700 has-[:checked]:shadow-sm has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-laut-400">
            <input type="radio" name="portal" value="{{ $v }}" class="sr-only" @checked(old('portal', 'pengguna_jasa') === $v)>{{ $l }}
          </label>
        @endforeach
      </div>
      <p class="hint">Operator: Admin Lab dan petugas laboratorium.</p>
    </fieldset>
    <div>
      <label for="email" class="label">Email</label>
      <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" autocomplete="username" required autofocus @error('email') aria-invalid="true" @enderror>
    </div>
    <div>
      <label for="password" class="label">Kata sandi</label>
      <div class="relative">
        <input id="password" name="password" type="password" class="input pr-12" autocomplete="current-password" required>
        <button type="button" class="absolute inset-y-0 right-0 px-3 text-kabut-500 hover:text-tinta" data-toggle-password="password" aria-label="Tampilkan kata sandi"><x-ikon n="mata" class="h-5 w-5" /></button>
      </div>
    </div>
    <button class="btn-primary w-full py-3">Masuk</button>
    <p class="text-xs text-kabut-500">Akun dikunci {{ config('sipelab.lockout_minutes') }} menit setelah {{ config('sipelab.lockout_attempts') }} kali gagal masuk berturut-turut. Lupa kata sandi? Hubungi Admin Balai untuk reset.</p>
  </form>
</div>
@endsection
