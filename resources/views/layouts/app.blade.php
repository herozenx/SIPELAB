@php
  $u = auth()->user();
  $R = \App\Enums\Role::class;
  $nav = [];
  $nav[] = ['dashboard', 'Beranda', 'beranda', 'dashboard'];
  if ($u->role === $R::PenggunaJasa) {
      $nav[] = ['permohonan.create', 'Ajukan uji', 'tambah', 'permohonan.create'];
      $nav[] = ['permohonan.index', 'Permohonan saya', 'daftar', 'permohonan.index|permohonan.show|permohonan.lhu'];
      $nav[] = ['chat', 'Chat petugas', 'chat', 'chat'];
      $nav[] = ['panduan', 'Panduan', 'buku', 'panduan'];
  }
  if ($u->isStaff()) {
      $nav[] = ['permohonan.index', 'Semua permohonan', 'daftar', 'permohonan.index|permohonan.show|permohonan.lhu'];
      $nav[] = ['chat', 'Chat pengguna', 'chat', 'chat'];
      $nav[] = ['tim.index', 'Tim operator', 'tim', 'tim.*'];
      $nav[] = ['laporan', 'Laporan', 'grafik', 'laporan'];
  }
  if ($u->role === $R::PetugasLab) {
      $nav[] = ['riwayat', 'Riwayat uji', 'riwayat', 'riwayat'];
      $nav[] = ['chat', 'Chat pengguna', 'chat', 'chat'];
  }
  $chatBelum = app(\App\Services\Chat::class)->belum($u);
  $admin = $u->role === $R::Superadmin ? [
      ['admin.users', 'Pengguna', 'pengguna', 'admin.users'],
      ['admin.labs', 'Lab & tarif', 'lab', 'admin.labs'],
      ['admin.settings', 'Pengaturan', 'atur', 'admin.settings'],
      ['admin.audit', 'Log aktivitas', 'log', 'admin.audit'],
  ] : [];
  $aktif = fn ($pola) => request()->routeIs(...explode('|', $pola));
@endphp
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Beranda') · SIPELAB</title>
  <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
  <script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}" defer></script>
</head>
<body @hasSection('tanpa-poll') @else data-poll-url="{{ route('api.versi') }}" @endif data-poll-seconds="{{ config('sipelab.poll_seconds') }}" @hasSection('poll-id') data-poll-id="@yield('poll-id')" @endif data-chat-status-url="{{ route('chat.status') }}">
<a href="#isi" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Langsung ke isi</a>

<div class="lg:grid lg:min-h-screen lg:grid-cols-[248px_minmax(0,1fr)]">
  {{-- Navigasi samping --}}
  <aside id="nav" class="no-print fixed inset-y-0 left-0 z-40 hidden w-[248px] flex-col bg-laut-700 px-3 pb-4 pt-5 text-white lg:sticky lg:top-0 lg:flex lg:h-screen" data-nav>
    <a href="{{ route('dashboard') }}" class="mb-7 flex items-center gap-2.5 px-3 text-white no-underline hover:no-underline">
      <img src="{{ asset('favicon.svg') }}" alt="" class="h-8 w-8">
      <span class="leading-tight"><span class="block text-[1.05rem] font-extrabold tracking-tight">SIPELAB</span><span class="block text-2xs font-medium text-laut-200">BPPMHKP Surabaya II</span></span>
    </a>
    <nav class="flex flex-1 flex-col gap-1 overflow-y-auto" aria-label="Menu utama">
      @foreach ($nav as [$route, $label, $ikon, $pola])
        <a href="{{ route($route) }}" class="nav-link" @if($aktif($pola) && !($route === 'permohonan.index' && request()->routeIs('permohonan.create'))) aria-current="page" @endif><x-ikon :n="$ikon" /><span class="flex-1">{{ $label }}</span>
          @if ($route === 'chat')<span class="num rounded-full bg-karang-600 px-1.5 py-0.5 text-2xs font-bold leading-none text-white {{ $chatBelum ? '' : 'hidden' }}" data-chat-badge aria-label="pesan belum dibaca">{{ $chatBelum }}</span>@endif
        </a>
      @endforeach
      @if ($admin)
        <p class="mb-1 mt-5 px-3 text-2xs font-semibold text-laut-300">Superadmin</p>
        @foreach ($admin as [$route, $label, $ikon, $pola])
          <a href="{{ route($route) }}" class="nav-link" @if($aktif($pola)) aria-current="page" @endif><x-ikon :n="$ikon" />{{ $label }}</a>
        @endforeach
      @endif
    </nav>
    <div class="mt-4 border-t border-white/10 pt-4">
      <a href="{{ route('akun') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-white no-underline hover:bg-white/10 hover:no-underline" @if(request()->routeIs('akun')) aria-current="page" @endif>
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-laut-500 text-xs font-bold">{{ $u->initials() }}</span>
        <span class="min-w-0 leading-tight"><span class="block truncate text-sm font-semibold">{{ $u->nama }}</span><span class="block truncate text-2xs text-laut-200">{{ $u->role->label() }}@if($u->lab), {{ $u->lab->nama }}@endif</span></span>
      </a>
      <form method="post" action="{{ route('logout') }}" class="mt-1">@csrf
        <button class="nav-link w-full">  <x-ikon n="keluar" />Keluar</button>
      </form>
    </div>
  </aside>
  <div class="fixed inset-0 z-30 hidden bg-tinta/40 lg:hidden" data-nav-backdrop></div>

  <div class="min-w-0">
    {{-- Bilah atas untuk layar kecil --}}
    <header class="no-print sticky top-0 z-20 flex items-center justify-between border-b border-kabut-200 bg-white px-4 py-3 lg:hidden">
      <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-extrabold text-laut-700 no-underline"><img src="{{ asset('favicon.svg') }}" alt="" class="h-7 w-7">SIPELAB</a>
      <button type="button" class="btn-ghost btn-sm" data-nav-toggle aria-controls="nav" aria-expanded="false"><x-ikon n="menu" class="h-4 w-4" />Menu</button>
    </header>

    <main id="isi" class="mx-auto w-full max-w-[1180px] px-4 {{ $u->role === $R::PenggunaJasa ? 'pb-28' : 'pb-16' }} pt-6 sm:px-6 lg:px-10 lg:pt-9">
      <div class="no-print fixed bottom-4 left-1/2 z-50 hidden -translate-x-1/2" data-refresh-bar>
        <div class="flex items-center gap-3 rounded-full bg-tinta px-4 py-2.5 text-sm text-white shadow-lg">
          <x-ikon n="info" class="h-4 w-4" /> Ada pembaruan data.
          <button type="button" class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-tinta" data-refresh-now>Muat ulang</button>
        </div>
      </div>
      @include('partials.flash')
      @yield('content')
    </main>
    {{-- Tombol chat melayang untuk pengguna jasa --}}
    @if ($u->role === $R::PenggunaJasa && ! request()->routeIs('chat'))
      {{-- HP: tombol bulat ikon saja agar tidak menutupi isi; layar lebar: dengan tulisan --}}
      <a href="{{ route('chat') }}" aria-label="Chat petugas" class="no-print fixed bottom-4 right-4 z-40 inline-flex h-14 w-14 items-center justify-center gap-2 rounded-full bg-laut-600 text-sm font-semibold text-white no-underline shadow-lg hover:bg-laut-700 hover:no-underline sm:bottom-5 sm:right-5 sm:h-auto sm:w-auto sm:px-5 sm:py-3.5">
        <x-ikon n="chat" class="h-6 w-6 sm:h-5 sm:w-5" /><span class="hidden sm:inline">Chat petugas</span>
        <span class="num absolute -right-0.5 -top-0.5 rounded-full bg-karang-600 px-1.5 py-0.5 text-2xs font-bold leading-none ring-2 ring-white sm:static sm:ring-0 {{ $chatBelum ? '' : 'hidden' }}" data-chat-badge>{{ $chatBelum }}</span>
      </a>
    @endif
  </div>
</div>
</body>
</html>
