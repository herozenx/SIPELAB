<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'SIPELAB') · BPPMHKP Surabaya II</title>
  <meta name="description" content="Layanan permohonan uji mutu hasil perikanan secara online: ajukan, bayar lewat SIMPONI, pantau pengujian, dan terima LHU digital.">
  <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
  <script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}" defer></script>
</head>
<body class="bg-white">
  <a href="#isi" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-laut-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">Lompat ke konten</a>

  <header class="sticky top-0 z-40 border-b border-kabut-200 bg-white">
    <div class="mx-auto flex h-[68px] max-w-6xl items-center justify-between gap-2 px-4 sm:px-6">
      <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3 text-tinta no-underline hover:no-underline" aria-label="SIPELAB, beranda">
        <img src="{{ asset('favicon.svg') }}" alt="" class="h-9 w-9">
        <span class="leading-tight">
          <span class="block font-extrabold tracking-tight text-laut-700">SIPELAB</span>
          <span class="block text-2xs font-medium text-kabut-500">BPPMHKP Surabaya II</span>
        </span>
      </a>

      <nav class="hidden items-center gap-1 text-sm md:flex" aria-label="Menu utama">
        <a href="{{ route('panduan') }}" class="rounded-lg px-3 py-2.5 font-semibold text-kabut-600 no-underline hover:bg-kabut-100 hover:text-tinta hover:no-underline">Panduan</a>
        <a href="{{ route('home') }}#tarif" class="rounded-lg px-3 py-2.5 font-semibold text-kabut-600 no-underline hover:bg-kabut-100 hover:text-tinta hover:no-underline">Tarif</a>
        <a href="{{ route('verifikasi') }}" class="rounded-lg px-3 py-2.5 font-semibold text-kabut-600 no-underline hover:bg-kabut-100 hover:text-tinta hover:no-underline">Cek keaslian LHU</a>
        <span class="mx-2 h-6 w-px bg-kabut-200" aria-hidden="true"></span>
        @auth
          <a href="{{ route('dashboard') }}" class="btn-primary min-h-[44px] px-5">Buka beranda</a>
        @else
          <a href="{{ route('login') }}" class="rounded-lg px-3 py-2.5 font-semibold text-tinta no-underline hover:bg-kabut-100 hover:no-underline">Masuk</a>
          <a href="{{ route('register') }}" class="btn-primary ml-1 min-h-[44px] px-5">Daftar</a>
        @endauth
      </nav>

      <button type="button" data-menu-toggle class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-tinta hover:bg-kabut-100 md:hidden" aria-label="Buka menu" aria-expanded="false" aria-controls="menu-hp">
        <svg data-menu-open viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        <svg data-menu-close viewBox="0 0 24 24" class="hidden h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </div>

    <div id="menu-hp" data-menu class="hidden border-t border-kabut-200 bg-white px-4 pb-6 pt-2 md:hidden">
      <nav class="flex flex-col" aria-label="Menu seluler">
        <a href="{{ route('panduan') }}" class="py-3 text-base font-semibold text-tinta no-underline">Panduan</a>
        <a href="{{ route('home') }}#tarif" class="py-3 text-base font-semibold text-tinta no-underline">Tarif</a>
        <a href="{{ route('verifikasi') }}" class="py-3 text-base font-semibold text-tinta no-underline">Cek keaslian LHU</a>
        <div class="mt-4 flex flex-col gap-2">
          @auth
            <a href="{{ route('dashboard') }}" class="btn-primary min-h-[48px]">Buka beranda</a>
          @else
            <a href="{{ route('login') }}" class="btn-ghost min-h-[48px]">Masuk</a>
            <a href="{{ route('register') }}" class="btn-primary min-h-[48px]">Daftar</a>
          @endauth
        </div>
      </nav>
    </div>
  </header>

  <main id="isi">
    @yield('content')
  </main>

  <footer class="bg-laut-800 text-laut-100">
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
      <div class="grid gap-10 md:grid-cols-[1fr_auto]">
        <div>
          <div class="flex items-center gap-3">
            <img src="{{ asset('favicon.svg') }}" alt="" class="h-9 w-9">
            <span class="leading-tight">
              <span class="block font-extrabold tracking-tight text-white">SIPELAB</span>
              <span class="block text-2xs font-medium text-laut-200">BPPMHKP Surabaya II</span>
            </span>
          </div>
          <p class="mt-6 text-sm font-semibold text-white">{{ \App\Models\Setting::get('nama_instansi') }}</p>
          <p class="mt-1 text-sm text-laut-200">{{ \App\Models\Setting::get('alamat_instansi') }}</p>
        </div>
        <nav class="flex flex-col gap-1 text-sm" aria-label="Menu footer">
          <a href="{{ route('panduan') }}" class="py-2 text-laut-200 no-underline hover:text-white hover:no-underline">Panduan</a>
          <a href="{{ route('home') }}#tarif" class="py-2 text-laut-200 no-underline hover:text-white hover:no-underline">Tarif</a>
          <a href="{{ route('verifikasi') }}" class="py-2 text-laut-200 no-underline hover:text-white hover:no-underline">Cek keaslian LHU</a>
          <a href="{{ route('home') }}#bantuan" class="py-2 text-laut-200 no-underline hover:text-white hover:no-underline">Pusat bantuan</a>
        </nav>
      </div>
      <div class="mt-10 border-t border-white/10 pt-6 text-xs text-laut-300">
        <p>© {{ date('Y') }} {{ \App\Models\Setting::get('nama_instansi') }}</p>
      </div>
    </div>
  </footer>
</body>
</html>
