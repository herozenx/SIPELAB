<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('judul') · SIPELAB</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="grid min-h-screen place-items-center bg-kabut-100 px-4">
  <main class="max-w-md text-center">
    <img src="{{ asset('favicon.svg') }}" alt="" class="mx-auto h-12 w-12">
    <p class="num mt-6 text-sm font-bold text-laut-500">Kode @yield('kode')</p>
    <h1 class="mt-1">@yield('judul')</h1>
    <p class="mt-2 text-kabut-600">@yield('isi')</p>
    <a href="{{ url('/') }}" class="btn-primary mt-6">Ke halaman utama</a>
  </main>
</body>
</html>
