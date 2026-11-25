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
      @if ($user->alamat)<div><dt class="text-kabut-500">Alamat</dt><dd class="mt-0.5">{{ $user->alamat }}</dd></div>@endif
      @if ($user->induk)<div><dt class="text-kabut-500">Akun perusahaan</dt><dd class="mt-0.5 font-semibold">Operator dari {{ $user->induk->nama }}<span class="block text-xs font-normal text-kabut-500">Permohonan perusahaan ini saling terlihat oleh admin dan semua operatornya.</span></dd></div>@endif
      @if ($user->adminPerusahaan())<div><dt class="text-kabut-500">Akun perusahaan</dt><dd class="mt-0.5 font-semibold">Admin perusahaan</dd></div>@endif
      @if ($user->nik)<div><dt class="text-kabut-500">NIK</dt><dd class="num mt-0.5 font-semibold">{{ $user->nik }}</dd></div>@endif
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

@if ($user->adminPerusahaan())
  <section class="panel mt-6 overflow-hidden" id="operator">
    <div class="panel-head"><h2>Operator perusahaan</h2><span class="text-xs text-kabut-500">{{ $operator->count() }}/{{ $maksOperator }}</span></div>
    <p class="panel-body !pb-0 text-sm text-kabut-600">Operator adalah personel yang dihubungi Balai. Mereka masuk dengan akunnya sendiri, bisa mengajukan uji, dan melihat semua permohonan perusahaan.</p>
    @if ($operator->isNotEmpty())
      <div class="overflow-x-auto"><table class="tabel mt-4">
        <thead><tr><th>Nama</th><th>NIK</th><th>Email</th><th>No. HP</th><th>Status</th><th class="sr-only">Aksi</th></tr></thead>
        <tbody>
          @foreach ($operator as $op)
            <tr>
              <td class="font-semibold">{{ $op->nama }}</td>
              <td class="num">{{ $op->nik }}</td>
              <td>{{ $op->email }}</td>
              <td class="num">{{ $op->telepon ?: '–' }}</td>
              <td><span class="{{ $op->aktif ? 'badge-ok' : 'badge-bad' }}">{{ $op->aktif ? 'Aktif' : 'Nonaktif' }}</span></td>
              <td class="text-right">
                <form method="post" action="{{ route('akun.operator.atur', $op) }}">@csrf
                  <button class="btn-ghost btn-sm" data-confirm="{{ $op->aktif ? 'Nonaktifkan' : 'Aktifkan kembali' }} operator {{ $op->nama }}?">{{ $op->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table></div>
    @endif
    @if ($operator->count() < $maksOperator)
      <form method="post" action="{{ route('akun.operator') }}" class="panel-body mt-2 grid gap-4 border-t border-kabut-200 sm:grid-cols-2" novalidate>
        @csrf
        <h3 class="text-sm font-bold sm:col-span-2">Tambah operator</h3>
        @foreach ([['nama', 'Nama lengkap', 'text', 'off', 100], ['nik', 'NIK (16 angka)', 'text', 'off', 16], ['email', 'Email (untuk masuk)', 'email', 'off', 120], ['telepon', 'No. HP (opsional)', 'tel', 'off', 20], ['password', 'Kata sandi operator', 'password', 'new-password', 128]] as [$f, $l, $t, $ac, $max])
          <div @class(['sm:col-span-2' => $f === 'password'])>
            <label for="op-{{ $f }}" class="label">{{ $l }}</label>
            <input id="op-{{ $f }}" name="op[{{ $f }}]" type="{{ $t }}" value="{{ $f === 'password' ? '' : old("op.$f") }}" maxlength="{{ $max }}" autocomplete="{{ $ac }}" class="input {{ in_array($f, ['nik', 'telepon']) ? 'num' : '' }}" @if($f !== 'telepon') required @endif @error("op.$f") aria-invalid="true" @enderror>
            @error("op.$f")<p class="error">{{ $message }}</p>@enderror
            @if ($f === 'password')<p class="hint">Minimal 8 karakter, huruf besar, huruf kecil, dan angka. Sampaikan ke operator secara pribadi.</p>@endif
          </div>
        @endforeach
        <div class="sm:col-span-2"><button class="btn-primary"><x-ikon n="tambah" class="h-4 w-4" />Tambah operator</button></div>
      </form>
    @endif
  </section>
@endif
@endsection
