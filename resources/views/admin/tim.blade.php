@extends('layouts.app')
@section('title', 'Tim operator')
@section('content')
@php $e = $edit; @endphp
<h1>Tim operator lab</h1>
<p class="mt-1 max-w-prose text-kabut-600">Tambahkan operator untuk tiap tim lab. Operator masuk lewat pilihan <span class="font-semibold">Operator</span> di halaman login, hanya melihat antrean lab timnya, dan namanya tercatat pada sampel yang ia tangani.</p>

@if (session('sandi_sementara'))
  <div class="alert-warning mt-5" role="status"><x-ikon n="kunci" class="mt-0.5 h-4 w-4 shrink-0" />
    <div>Kata sandi sementara untuk <span class="font-semibold">{{ session('sandi_untuk') }}</span>: <span class="num select-all rounded bg-white px-2 py-0.5 text-base font-bold text-tinta">{{ session('sandi_sementara') }}</span>
    <p class="mt-1 text-xs">Hanya ditampilkan sekali. Sampaikan langsung ke operator; ia wajib menggantinya saat pertama masuk.</p></div>
  </div>
@endif

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
  <div class="space-y-6">
    @foreach ($labs as $lab)
      <section class="panel overflow-hidden">
        <div class="panel-head">
          <div>
            <h2>Tim Lab {{ $lab->nama }} @unless($lab->aktif)<span class="badge-bad ml-1 align-middle">Lab nonaktif</span>@endunless</h2>
            <p class="mt-0.5 text-xs text-kabut-500">Urutan {{ $lab->urutan }} · norma {{ $lab->norma_hari }} hari kerja · {{ $lab->operator->where('aktif', true)->count() }} operator aktif, {{ $lab->operator->filter->online()->count() }} online</p>
          </div>
        </div>
        @if ($lab->operator->isEmpty())
          <p class="px-5 py-6 text-sm text-kabut-500">Belum ada operator di tim ini. Tambahkan lewat formulir di samping.</p>
        @else
          <ul class="divide-y divide-kabut-100">
            @foreach ($lab->operator as $op)
              <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 {{ $op->aktif ? '' : 'opacity-60' }}">
                <span class="flex min-w-0 items-center gap-3">
                  <span class="relative grid h-9 w-9 shrink-0 place-items-center rounded-full bg-laut-100 text-xs font-bold text-laut-700">{{ $op->initials() }}
                    <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full ring-2 ring-white {{ $op->online() ? 'bg-lamun-500' : 'bg-kabut-300' }}" title="{{ $op->online() ? 'Online' : 'Offline' }}"></span>
                  </span>
                  <span class="min-w-0">
                    <span class="block truncate font-semibold">{{ $op->nama }}</span>
                    <span class="block truncate text-xs text-kabut-500">{{ $op->email }} · {{ $op->online() ? 'online' : ($op->last_seen_at ? 'terakhir aktif '.$op->last_seen_at->locale('id')->diffForHumans() : 'belum pernah masuk') }}</span>
                  </span>
                </span>
                <span class="flex flex-wrap items-center gap-2">
                  @if (($beban[$op->id] ?? 0) > 0)<span class="badge-lab">Menangani {{ $beban[$op->id] }} sampel</span>@endif
                  @if (! $op->aktif)<span class="badge-bad">Nonaktif</span>
                  @elseif ($op->locked_until && $op->locked_until->isFuture())<span class="badge-wait">Terkunci</span>
                  @elseif ($op->wajib_ganti_password)<span class="badge-info">Sandi sementara</span>@endif
                  <a href="{{ route('tim.index', ['edit' => $op->id]) }}" class="btn-ghost btn-sm">Ubah</a>
                </span>
              </li>
            @endforeach
          </ul>
        @endif
      </section>
    @endforeach

    @if ($tanpaLab->isNotEmpty())
      <section class="panel">
        <div class="panel-head"><h2>Operator tanpa tim</h2></div>
        <ul class="divide-y divide-kabut-100">
          @foreach ($tanpaLab as $op)
            <li class="flex items-center justify-between gap-3 px-5 py-3"><span><span class="font-semibold">{{ $op->nama }}</span><span class="block text-xs text-kabut-500">{{ $op->email }}</span></span><a href="{{ route('tim.index', ['edit' => $op->id]) }}" class="btn-ghost btn-sm">Tempatkan</a></li>
          @endforeach
        </ul>
      </section>
    @endif
  </div>

  <section class="panel self-start xl:sticky xl:top-6">
    <div class="panel-head"><h2>{{ $e ? 'Ubah operator' : 'Tambah operator' }}</h2>@if($e)<a href="{{ route('tim.index') }}" class="text-sm font-semibold">Batal</a>@endif</div>
    <form method="post" action="{{ $e ? route('tim.update', $e) : route('tim.store') }}" class="panel-body space-y-4" novalidate data-guard>
      @csrf @if($e) @method('put') @endif
      <div>
        <label for="t-nama" class="label">Nama operator</label>
        <input id="t-nama" name="nama" value="{{ old('nama', $e?->nama) }}" class="input" required @error('nama') aria-invalid="true" @enderror>
        @error('nama')<p class="error">{{ $message }}</p>@enderror
      </div>
      <div>
        <label for="t-email" class="label">Email (untuk login)</label>
        <input id="t-email" name="email" type="email" value="{{ old('email', $e?->email) }}" class="input" required autocomplete="off" @error('email') aria-invalid="true" @enderror>
        @error('email')<p class="error">{{ $message }}</p>@enderror
      </div>
      <div>
        <label for="t-lab" class="label">Tim lab</label>
        <select id="t-lab" name="lab_id" class="input" required @error('lab_id') aria-invalid="true" @enderror>
          <option value="">Pilih tim</option>
          @foreach ($labs as $l)<option value="{{ $l->id }}" @selected((int) old('lab_id', $e?->lab_id ?? request('lab')) === $l->id)>Lab {{ $l->nama }}</option>@endforeach
        </select>
        @error('lab_id')<p class="error">{{ $message }}</p>@enderror
      </div>
      <div>
        <label for="t-telp" class="label">Telepon/WA <span class="font-normal text-kabut-500">(opsional)</span></label>
        <input id="t-telp" name="telepon" value="{{ old('telepon', $e?->telepon) }}" class="input num">
        @error('telepon')<p class="error">{{ $message }}</p>@enderror
      </div>
      @if ($e)
        <label class="flex items-center gap-2 text-sm font-semibold"><input type="hidden" name="aktif" value="0"><input type="checkbox" name="aktif" value="1" class="h-4 w-4 accent-laut-600" @checked(old('aktif', $e->aktif))> Akun aktif</label>
      @else
        <p class="hint">Kata sandi sementara dibuat otomatis dan ditampilkan sekali setelah disimpan.</p>
      @endif
      <button class="btn-primary w-full">{{ $e ? 'Simpan perubahan' : 'Tambah ke tim' }}</button>
    </form>
    @if ($e)
      <div class="flex flex-wrap gap-2 border-t border-kabut-200 px-5 py-4">
        <form method="post" action="{{ route('tim.reset', $e) }}">@csrf<button class="btn-danger btn-sm" data-confirm="Reset kata sandi {{ $e->nama }}? Semua sesinya akan dikeluarkan.">Reset kata sandi</button></form>
        @if ($e->locked_until && $e->locked_until->isFuture())
          <form method="post" action="{{ route('tim.unlock', $e) }}">@csrf<button class="btn-ghost btn-sm">Buka kunci akun</button></form>
        @endif
      </div>
    @endif
  </section>
</div>
@endsection
