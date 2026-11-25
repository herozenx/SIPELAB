<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'SILANTRO') | BPPMHKP Surabaya II</title>
  <meta name="description" content="Layanan permohonan uji mutu hasil perikanan secara online: ajukan, bayar lewat SIMPONI, pantau pengujian, dan terima LHP digital.">
  <link rel="icon" href="{{ asset('img/logo-kkp.png') }}" type="image/png">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
  <script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}" defer></script>
</head>
<body class="bg-white">
  <a href="#isi" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:min-h-[44px] focus:rounded-lg focus:bg-laut-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">Lewati ke konten utama</a>

  <header class="sticky top-0 z-40 border-b border-kabut-200 bg-white/95 shadow-[0_1px_8px_rgba(19,35,43,0.06)] backdrop-blur-sm">
    <div class="mx-auto grid h-[72px] max-w-6xl grid-cols-[1fr_auto] items-center gap-4 px-4 sm:px-6 lg:grid-cols-[1fr_auto_1fr]">
      <a href="{{ route('home') }}" class="flex min-h-[44px] shrink-0 items-center gap-3 text-tinta no-underline hover:no-underline" aria-label="SILANTRO, beranda">
        <img src="{{ asset('img/logo-kkp.png') }}" alt="Logo KKP" width="40" height="36" class="h-9 w-auto object-contain">
        <span class="leading-tight">
          <span class="block font-extrabold tracking-tight text-laut-700">SILANTRO</span>
          <span class="block text-2xs font-medium text-kabut-500">BPPMHKP Surabaya II</span>
        </span>
      </a>

      <nav class="hidden items-center gap-1 text-sm md:flex" aria-label="Menu utama">
        <a href="{{ route('panduan') }}" class="public-nav-link">Panduan</a>
        <a href="{{ route('home') }}#tarif" class="public-nav-link">Tarif</a>
        <a href="{{ route('verifikasi') }}" class="public-nav-link">Cek Keaslian LHP</a>
        <a href="{{ route('home') }}#bantuan" class="public-nav-link">Pusat Bantuan</a>
      </nav>

      <div class="hidden items-center justify-end gap-3 lg:flex">
        @auth
          <a href="{{ route('dashboard') }}" class="btn-primary min-h-[44px] rounded-lg px-5 transition-all duration-200 hover:-translate-y-0.5">Buka Beranda</a>
        @else
          <a href="{{ route('login') }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-kabut-300 bg-white px-4 text-sm font-bold text-laut-700 no-underline shadow-[0_2px_8px_rgba(19,35,43,0.06)] transition-all duration-200 hover:border-laut-300 hover:bg-kabut-50 hover:no-underline">Masuk</a>
          <a href="{{ route('register') }}" class="btn-primary min-h-[44px] rounded-lg px-5 transition-all duration-200 hover:-translate-y-0.5">Daftar</a>
        @endauth
      </div>

      <button type="button" data-menu-toggle class="inline-flex h-11 min-w-11 items-center justify-center gap-2 rounded-lg px-3 text-tinta hover:bg-kabut-100 md:hidden" aria-label="Buka menu" aria-expanded="false" aria-controls="menu-hp">
        <svg data-menu-open viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        <svg data-menu-close viewBox="0 0 24 24" class="hidden h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        <span class="text-sm font-semibold">Menu</span>
      </button>
    </div>

    <div id="menu-hp" data-menu class="public-mobile-menu md:hidden" aria-hidden="true">
      <nav class="mx-auto flex max-w-6xl flex-col px-4 pb-5" aria-label="Menu seluler">
        <a href="{{ route('panduan') }}" class="mobile-nav-link">Panduan</a>
        <a href="{{ route('home') }}#tarif" class="mobile-nav-link">Tarif</a>
        <a href="{{ route('verifikasi') }}" class="mobile-nav-link">Cek Keaslian LHP</a>
        <a href="{{ route('home') }}#bantuan" class="mobile-nav-link">Pusat Bantuan</a>
        <div class="mt-4 flex flex-col gap-2">
          @auth
            <a href="{{ route('dashboard') }}" class="btn-primary min-h-[48px]">Buka Beranda</a>
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
    <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-16">
      <div class="grid gap-10 md:grid-cols-[1fr_220px] md:gap-16">
        <div>
          <a href="{{ route('home') }}" class="flex min-h-[44px] items-center gap-3 text-white no-underline hover:no-underline" aria-label="SILANTRO, kembali ke beranda">
            <img src="{{ asset('img/logo-kkp.png') }}" alt="Logo KKP" width="40" height="36" class="h-9 w-auto object-contain">
            <span class="leading-tight">
              <span class="block font-extrabold tracking-tight text-white">SILANTRO</span>
              <span class="block text-2xs font-medium text-laut-200">BPPMHKP Surabaya II</span>
            </span>
          </a>
          <p class="mt-6 text-sm font-semibold text-white">{{ \App\Models\Setting::get('nama_instansi') }}</p>
          <address class="mt-2 max-w-md not-italic text-sm leading-relaxed text-laut-200">{{ \App\Models\Setting::get('alamat_instansi') }}</address>
        </div>
        <nav class="flex flex-col text-sm" aria-label="Menu footer">
          <a href="{{ route('panduan') }}" class="flex min-h-[44px] items-center border-b border-white/10 text-laut-200 no-underline transition-colors duration-200 hover:pl-1 hover:text-white hover:no-underline">Panduan</a>
          <a href="{{ route('home') }}#tarif" class="flex min-h-[44px] items-center border-b border-white/10 text-laut-200 no-underline transition-colors duration-200 hover:pl-1 hover:text-white hover:no-underline">Tarif</a>
          <a href="{{ route('verifikasi') }}" class="flex min-h-[44px] items-center border-b border-white/10 text-laut-200 no-underline transition-colors duration-200 hover:pl-1 hover:text-white hover:no-underline">Cek Keaslian LHP</a>
          <a href="{{ route('home') }}#bantuan" class="flex min-h-[44px] items-center text-laut-200 no-underline transition-colors duration-200 hover:pl-1 hover:text-white hover:no-underline">Pusat Bantuan</a>
        </nav>
      </div>
      <div class="mt-10 border-t border-white/10 pt-6 text-xs text-laut-300">
        <p>© {{ date('Y') }} {{ \App\Models\Setting::get('nama_instansi') }}</p>
      </div>
    </div>
  </footer>
</body>
</html>
