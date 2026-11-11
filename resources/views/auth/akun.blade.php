@extends('layouts.app')
@section('title', 'Akun saya')
@section('content')
<h1>Akun saya</h1>
<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_1.1fr]">
  <section class="panel">
    <div class="panel-head"><h2>Profil</h2></div>
    <dl class="panel-body grid gap-4 text-sm">
      <div><dt class="text-kabut-500">Nama</dt><dd class="mt-0.5 font-semibold">{{ $user->nama }}</dd></div>
      <div><dt class="text-kabut-500">Email</dt><dd class="mt-0.5 font-semibold">{{ $user->email }}</dd></div>
      <div><dt class="text-kabut-500">Peran</dt><dd class="mt-0.5 font-semibold">{{ $user->role->label() }}@if($user->lab), Lab {{ $user->lab->nama }}@endif</dd></div>
      @if ($user->instansi)<div><dt class="text-kabut-500">Instansi</dt><dd class="mt-0.5 font-semibold">{{ $user->instansi }}</dd></div>@endif
      @if ($user->telepon)<div><dt class="text-kabut-500">Telepon</dt><dd class="num mt-0.5 font-semibold">{{ $user->telepon }}</dd></div>@endif
      <div><dt class="text-kabut-500">Terakhir masuk</dt><dd class="mt-0.5">{{ tanggal($user->last_login_at) }}@if($user->last_login_ip) dari IP <span class="num">{{ $user->last_login_ip }}</span>@endif</dd></div>
    </dl>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Ganti kata sandi</h2></div>
    <form method="post" action="{{ route('akun.password') }}" class="panel-body space-y-4" novalidate>
      @csrf @method('put')
      @if ($user->wajib_ganti_password)
        <p class="alert-warning"><x-ikon n="kunci" class="mt-0.5 h-4 w-4 shrink-0" />Anda masih memakai kata sandi sementara dari admin. Ganti sekarang untuk bisa memakai aplikasi.</p>
      @endif
      <div>
        <label for="current_password" class="label">Kata sandi saat ini</label>
        <input id="current_password" name="current_password" type="password" class="input" autocomplete="current-password" required @error('current_password') aria-invalid="true" @enderror>
      </div>
      <div>
        <label for="password" class="label">Kata sandi baru</label>
        <input id="password" name="password" type="password" class="input" autocomplete="new-password" required @error('password') aria-invalid="true" @enderror>
        <p class="hint">Minimal 8 karakter, berisi huruf besar, huruf kecil, dan angka.</p>
      </div>
      <div>
        <label for="password_confirmation" class="label">Ulangi kata sandi baru</label>
        <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
      </div>
      <button class="btn-primary">Simpan kata sandi baru</button>
      <p class="text-xs text-kabut-500">Setelah diganti, sesi di perangkat lain otomatis dikeluarkan.</p>
    </form>
  </section>
</div>
@endsection
