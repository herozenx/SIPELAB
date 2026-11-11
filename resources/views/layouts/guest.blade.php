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
  <header class="border-b border-kabut-200">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-2 px-4 py-3.5 sm:gap-4 sm:px-6">
      <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5 text-tinta no-underline hover:no-underline">
        <img src="{{ asset('favicon.svg') }}" alt="" class="h-8 w-8">
        <span class="leading-tight"><span class="block font-extrabold tracking-tight text-laut-700">SIPELAB</span><span class="hidden text-2xs text-kabut-500 sm:block">BPPMHKP Surabaya II</span></span>
      </a>
      <nav class="flex items-center gap-0.5 text-sm sm:gap-2" aria-label="Menu">
        <a href="{{ route('panduan') }}" class="rounded-lg px-2.5 py-2 font-semibold text-tinta no-underline hover:bg-kabut-100 sm:px-3">Panduan</a>
        <a href="{{ route('verifikasi') }}" class="hidden rounded-lg px-3 py-2 font-semibold text-tinta no-underline hover:bg-kabut-100 sm:block">Cek keaslian LHU</a>
        @auth
          <a href="{{ route('dashboard') }}" class="btn-primary btn-sm">Buka beranda</a>
        @else
          <a href="{{ route('login') }}" class="rounded-lg px-2.5 py-2 font-semibold text-tinta no-underline hover:bg-kabut-100 sm:px-3">Masuk</a>
          <a href="{{ route('register') }}" class="btn-primary btn-sm">Daftar</a>
        @endauth
      </nav>
    </div>
  </header>
  <main id="isi">
    @yield('content')
  </main>
  <footer class="border-t border-kabut-200 bg-kabut-50">
    <div class="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-6 text-xs text-kabut-500 sm:flex-row sm:justify-between sm:px-6">
      <p>{{ \App\Models\Setting::get('nama_instansi') }}</p>
      <p>{{ \App\Models\Setting::get('alamat_instansi') }}</p>
    </div>
  </footer>
</body>
</html>
