@extends('layouts.guest')
@section('title', 'Masuk')
@section('content')
<div class="bg-kabut-50 px-4 py-6 sm:px-6 sm:py-8">
  <div
    class="mx-auto grid w-full max-w-[900px] overflow-hidden rounded-xl border border-kabut-200 bg-white shadow-[0_18px_44px_rgba(11,61,80,0.14)] lg:grid-cols-[320px_minmax(0,1fr)]">
    <section
      class="relative hidden min-h-[620px] flex-col justify-center overflow-hidden bg-laut-700 px-8 py-8 text-white lg:flex"
      aria-label="Tentang masuk ke SILANTRO">
      <div class="mx-auto max-w-[250px]">
        <svg viewBox="0 0 560 400" class="mx-auto h-auto max-h-[220px] w-full" role="img"
          aria-label="Ilustrasi pengujian hasil perikanan dan penerbitan LHP">
          <circle cx="300" cy="200" r="185" fill="#0E4D64" />
          <g transform="rotate(-4 405 195)">
            <path d="M330 60 H452 L496 104 V316 Q496 328 484 328 H342 Q330 328 330 316 V72 Q330 60 342 60 Z"
              fill="#F7F9FA" />
            <path d="M452 60 L496 104 H462 Q452 104 452 94 Z" fill="#CBD5DA" />
            <rect x="354" y="104" width="118" height="11" rx="5.5" fill="#0B3D50" />
            <rect x="354" y="130" width="132" height="7" rx="3.5" fill="#CBD5DA" />
            <rect x="354" y="144" width="108" height="7" rx="3.5" fill="#CBD5DA" />
            <rect x="354" y="182" width="120" height="58" rx="8" fill="#EEF6F8" />
            <rect x="366" y="196" width="72" height="6" rx="3" fill="#A9D0DC" />
            <rect x="444" y="196" width="18" height="6" rx="3" fill="#1C6F8C" />
            <circle cx="468" cy="300" r="30" fill="#1C6F8C" />
            <circle cx="468" cy="300" r="22" fill="none" stroke="#A9D0DC" stroke-width="2" />
            <path d="M456 300l9 9 17-17" fill="none" stroke="#F7F9FA" stroke-width="5" stroke-linecap="round"
              stroke-linejoin="round" />
          </g>
          <path d="M232 246L286 210C276 234 276 258 286 282Z" fill="#3F8EA8" />
          <path d="M58 248C96 206 172 200 224 238C232 243 232 253 224 258C172 296 96 290 58 248Z" fill="#74B1C4" />
          <circle cx="90" cy="240" r="6.5" fill="#05202B" />
          <g fill="none" stroke="#74B1C4" stroke-width="3" stroke-linecap="round">
            <path d="M44 330Q84 316 124 330T204 330" />
            <path d="M84 356Q124 342 164 356T244 356" />
          </g>
        </svg>
        <h2 class="mt-3 text-lg font-extrabold leading-snug text-white">Lanjutkan layanan Anda.</h2>
        <p class="mt-2 text-sm leading-relaxed text-laut-100">Masuk untuk melihat permohonan yang sedang berjalan dan
          dokumen layanan yang telah tersedia.</p>
        <ul class="mt-7 space-y-3 border-t border-white/15 pt-5 text-sm text-laut-100">
          <li class="flex items-start gap-2">
            <x-ikon n="cek" class="mt-0.5 h-4 w-4 shrink-0 text-laut-300" /><span>Pantau status permohonan Anda.</span>
          </li>
          <li class="flex items-start gap-2">
            <x-ikon n="cek" class="mt-0.5 h-4 w-4 shrink-0 text-laut-300" /><span>Unduh dokumen layanan saat sudah
              tersedia.</span>
          </li>
        </ul>
      </div>
    </section>

    <section class="flex min-w-0 items-center justify-center px-5 py-6 sm:px-8" aria-labelledby="judul-masuk">
      <div class="w-full max-w-[480px]">
        <a href="{{ route('home') }}"
          class="mb-3 inline-flex min-h-[44px] items-center gap-2 rounded-lg px-1 text-sm font-semibold text-kabut-600 no-underline transition-colors duration-200 hover:text-laut-700 hover:no-underline">
          <x-ikon n="kembali" class="h-4 w-4" />
          Kembali
        </a>

        <div class="mb-5 flex items-center gap-3 rounded-xl border border-laut-100 bg-laut-50 p-3 lg:hidden">
          <img src="{{ asset('favicon.svg') }}" alt="" width="44" height="44" class="h-11 w-11 shrink-0">
          <div>
            <p class="text-xs font-bold text-laut-700">SILANTRO</p>
            <p class="mt-0.5 text-sm leading-snug text-kabut-600">Ajukan Permohonan Uji Lab</p>
          </div>
        </div>

        <header>
          <h1 id="judul-masuk" class="text-2xl font-extrabold leading-tight">Masuk ke SILANTRO</h1>

          <div class="mt-3">@include('partials.flash')</div>

          <form method="post" action="{{ url('/masuk') }}" class="mt-4 space-y-4" novalidate>
            @csrf

            <div>
              <label for="email" class="label">Email <span class="text-karang-600" aria-hidden="true">*</span><span
                  class="sr-only"> wajib</span></label>
              <input id="email" name="email" type="email" value="{{ old('email') }}"
                class="form-input input min-h-[48px]" autocomplete="username" inputmode="email" required autofocus
                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
              @error('email')<p id="email-error" class="error" role="alert">{{ $message }}</p>@enderror
            </div>

            <div>
              <label for="password" class="label">Kata sandi <span class="text-karang-600"
                  aria-hidden="true">*</span><span class="sr-only"> wajib</span></label>
              <div class="relative">
                <input id="password" name="password" type="password" class="form-input input min-h-[48px] pr-12"
                  autocomplete="current-password" required @error('password') aria-invalid="true"
                  aria-describedby="password-error" @enderror>
                <button type="button"
                  class="absolute inset-y-0 right-0 min-h-[48px] min-w-[48px] px-3 text-kabut-600 transition-colors duration-200 hover:text-tinta"
                  data-toggle-password="password" aria-label="Tampilkan kata sandi" aria-controls="password">
                  <span data-eye-open>
                    <x-ikon n="mata" class="h-5 w-5" />
                  </span>
                  <span data-eye-closed class="hidden">
                    <x-ikon n="mata-tutup" class="h-5 w-5" />
                  </span>
                </button>
              </div>
              @error('password')<p id="password-error" class="error" role="alert">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="btn-primary min-h-[48px] w-full py-3">Masuk</button>

            <div class="relative my-5 flex items-center" aria-hidden="true">
              <div class="w-full border-t border-kabut-200"></div>
              <span class="shrink-0 bg-white px-3 text-xs font-medium text-kabut-500">atau</span>
              <div class="w-full border-t border-kabut-200"></div>
            </div>

            <a href="{{ route('auth.google') }}"
              class="inline-flex min-h-[48px] w-full items-center justify-center gap-3 rounded-lg border border-kabut-300 bg-white px-4 text-sm font-semibold text-tinta no-underline shadow-xs transition-colors duration-200 hover:border-kabut-400 hover:bg-kabut-50 hover:no-underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-laut-500 active:translate-y-px">
              <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#4285F4"
                  d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                <path fill="#34A853"
                  d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                <path fill="#FBBC05"
                  d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" />
                <path fill="#EA4335"
                  d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
              </svg>
              Lanjutkan dengan Google
            </a>

            <p class="border-t border-kabut-200 pt-5 text-center text-sm text-kabut-600">
              Belum punya akun?
              <a href="{{ route('register') }}" class="auth-inline-link">Daftar</a>
            </p>
          </form>
      </div>
    </section>
  </div>
</div>
@endsection