@extends('layouts.app')
@section('title', 'Pengguna')
@section('content')
@php $R = \App\Enums\Role::class; $e = $edit; @endphp
<h1>Pengguna</h1>
<p class="mt-1 text-kabut-600">Kelola akun petugas dan pengguna jasa. Perubahan peran, lab, atau status langsung mengeluarkan sesi akun tersebut.</p>

@if (session('sandi_sementara'))
  <div class="alert-warning mt-5" role="status"><x-ikon n="kunci" class="mt-0.5 h-4 w-4 shrink-0" />
    <div>Kata sandi sementara: <span class="num select-all rounded bg-white px-2 py-0.5 text-base font-bold text-tinta">{{ session('sandi_sementara') }}</span>
    <p class="mt-1 text-xs">Hanya ditampilkan sekali. Sampaikan langsung ke pemilik akun; ia wajib menggantinya saat masuk.</p></div>
  </div>
@endif

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
  <section class="panel overflow-hidden">
    <form method="get" class="flex flex-col gap-3 border-b border-kabut-200 p-4 sm:flex-row">
      <label for="cari" class="sr-only">Cari</label>
      <input id="cari" name="cari" value="{{ $cari }}" class="input" placeholder="Cari nama, email, atau instansi">
      <label for="role" class="sr-only">Peran</label>
      <select id="role" name="role" class="input sm:w-48" data-autosubmit>
        <option value="">Semua peran</option>
        @foreach ($R::cases() as $r)<option value="{{ $r->value }}" @selected($role === $r)>{{ $r->label() }}</option>@endforeach
      </select>
      <button class="btn-ghost">Terapkan</button>
    </form>
    <div class="overflow-x-auto">
    <table class="tabel tabel-hp">
      <thead><tr><th>Nama</th><th>Peran</th><th>Status</th><th>Terakhir masuk</th><th></th></tr></thead>
      <tbody>
      @foreach ($rows as $usr)
        <tr>
          <td><span class="font-semibold">{{ $usr->nama }}</span><span class="block break-all text-xs text-kabut-500">{{ $usr->email }}@if($usr->instansi), {{ $usr->instansi }}@endif</span></td>
          <td class="whitespace-nowrap" data-label="Peran"><span>{{ $usr->role->label() }}@if($usr->lab)<span class="block text-xs text-kabut-500">Lab {{ $usr->lab->nama }}</span>@endif</span></td>
          <td class="whitespace-nowrap" data-label="Status">
            @if (! $usr->aktif)<span class="badge-bad">Nonaktif</span>
            @elseif ($usr->locked_until && $usr->locked_until->isFuture())<span class="badge-wait">Terkunci</span>
            @elseif ($usr->wajib_ganti_password)<span class="badge-info">Sandi sementara</span>
            @else<span class="badge-ok">Aktif</span>@endif
          </td>
          <td class="num whitespace-nowrap text-kabut-500" data-label="Terakhir masuk">{{ $usr->last_login_at ? tanggal($usr->last_login_at) : 'Belum pernah' }}</td>
          <td class="whitespace-nowrap text-right max-sm:!pt-2">
            <a href="{{ route('admin.users', ['edit' => $usr->id] + request()->only('cari', 'role', 'page')) }}" class="btn-ghost btn-sm">Ubah</a>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
    </div>
    {{ $rows->links() }}
  </section>

  <section class="panel self-start">
    <div class="panel-head"><h2>{{ $e ? 'Ubah akun' : 'Tambah akun' }}</h2>@if($e)<a href="{{ route('admin.users') }}" class="text-sm font-semibold">Batal</a>@endif</div>
    <form method="post" action="{{ $e ? route('admin.users.update', $e) : route('admin.users.store') }}" class="panel-body space-y-4" novalidate data-guard>
      @csrf @if($e) @method('put') @endif
      <div><label for="u-nama" class="label">Nama</label><input id="u-nama" name="nama" value="{{ old('nama', $e?->nama) }}" class="input" required></div>
      <div><label for="u-email" class="label">Email</label><input id="u-email" name="email" type="email" value="{{ old('email', $e?->email) }}" class="input" required autocomplete="off"></div>
      <div>
        <label for="u-role" class="label">Peran</label>
        <select id="u-role" name="role" class="input" data-reveal-select>
          @foreach ($R::cases() as $r)<option value="{{ $r->value }}" @selected(old('role', $e?->role->value ?? 'petugas_lab') === $r->value)>{{ $r->label() }}</option>@endforeach
        </select>
      </div>
      <div data-reveal-when="petugas_lab">
        <label for="u-lab" class="label">Ditempatkan di lab</label>
        <select id="u-lab" name="lab_id" class="input">
          <option value="">Pilih lab</option>
          @foreach ($labs as $l)<option value="{{ $l->id }}" @selected((int) old('lab_id', $e?->lab_id) === $l->id)>{{ $l->nama }}</option>@endforeach
        </select>
      </div>
      <div><label for="u-instansi" class="label">Instansi <span class="font-normal text-kabut-500">(opsional)</span></label><input id="u-instansi" name="instansi" value="{{ old('instansi', $e?->instansi) }}" class="input"></div>
      <div><label for="u-telp" class="label">Telepon <span class="font-normal text-kabut-500">(opsional)</span></label><input id="u-telp" name="telepon" value="{{ old('telepon', $e?->telepon) }}" class="input num"></div>
      <div>
        <label for="u-pass" class="label">{{ $e ? 'Kata sandi baru' : 'Kata sandi sementara' }} @if($e)<span class="font-normal text-kabut-500">(kosongkan bila tidak diganti)</span>@endif</label>
        <input id="u-pass" name="password" type="password" class="input" autocomplete="new-password" @unless($e) required @endunless>
        <p class="hint">Pemilik akun baru wajib mengganti sandi saat pertama masuk.</p>
      </div>
      <label class="flex items-center gap-2 text-sm font-semibold"><input type="hidden" name="aktif" value="0"><input type="checkbox" name="aktif" value="1" class="h-4 w-4 accent-laut-600" @checked(old('aktif', $e?->aktif ?? true))> Akun aktif</label>
      <button class="btn-primary w-full">{{ $e ? 'Simpan perubahan' : 'Buat akun' }}</button>
    </form>
    @if ($e)
      <div class="flex flex-wrap gap-2 border-t border-kabut-200 px-5 py-4">
        <form method="post" action="{{ route('admin.users.reset', $e) }}">@csrf<button class="btn-danger btn-sm" data-confirm="Reset kata sandi {{ $e->email }}? Semua sesinya akan dikeluarkan.">Reset kata sandi</button></form>
        @if ($e->locked_until && $e->locked_until->isFuture())
          <form method="post" action="{{ route('admin.users.unlock', $e) }}">@csrf<button class="btn-ghost btn-sm">Buka kunci akun</button></form>
        @endif
      </div>
    @endif
  </section>
</div>
@endsection
