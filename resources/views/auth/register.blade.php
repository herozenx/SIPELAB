@extends('layouts.guest')
@section('title', 'Daftar')
@section('content')
<div class="bg-kabut-50 px-4 py-6 sm:px-6 sm:py-8">
  <div class="mx-auto grid w-full max-w-[900px] overflow-hidden rounded-xl border border-kabut-200 bg-white shadow-[0_18px_44px_rgba(11,61,80,0.14)] lg:grid-cols-[320px_minmax(0,1fr)]">
    <section class="relative hidden min-h-[620px] flex-col justify-center overflow-hidden bg-laut-700 px-8 py-8 text-white lg:flex" aria-label="Tentang pendaftaran">
      <div class="mx-auto max-w-[250px]">
        <svg viewBox="0 0 560 400" class="mx-auto h-auto max-h-[220px] w-full" role="img" aria-label="Ilustrasi pengujian hasil perikanan dan penerbitan LHP">
          <circle cx="300" cy="200" r="185" fill="#0E4D64"/>
          <g transform="rotate(-4 405 195)">
            <path d="M330 60 H452 L496 104 V316 Q496 328 484 328 H342 Q330 328 330 316 V72 Q330 60 342 60 Z" fill="#F7F9FA"/>
            <path d="M452 60 L496 104 H462 Q452 104 452 94 Z" fill="#CBD5DA"/>
            <rect x="354" y="104" width="118" height="11" rx="5.5" fill="#0B3D50"/>
            <rect x="354" y="130" width="132" height="7" rx="3.5" fill="#CBD5DA"/>
            <rect x="354" y="144" width="108" height="7" rx="3.5" fill="#CBD5DA"/>
            <rect x="354" y="182" width="120" height="58" rx="8" fill="#EEF6F8"/>
            <rect x="366" y="196" width="72" height="6" rx="3" fill="#A9D0DC"/>
            <rect x="444" y="196" width="18" height="6" rx="3" fill="#1C6F8C"/>
            <circle cx="468" cy="300" r="30" fill="#1C6F8C"/>
            <circle cx="468" cy="300" r="22" fill="none" stroke="#A9D0DC" stroke-width="2"/>
            <path d="M456 300l9 9 17-17" fill="none" stroke="#F7F9FA" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
          </g>
          <path d="M232 246L286 210C276 234 276 258 286 282Z" fill="#3F8EA8"/>
          <path d="M58 248C96 206 172 200 224 238C232 243 232 253 224 258C172 296 96 290 58 248Z" fill="#74B1C4"/>
          <circle cx="90" cy="240" r="6.5" fill="#05202B"/>
          <g fill="none" stroke="#74B1C4" stroke-width="3" stroke-linecap="round">
            <path d="M44 330Q84 316 124 330T204 330"/>
            <path d="M84 356Q124 342 164 356T244 356"/>
          </g>
        </svg>

        <h2 class="mt-3 text-lg font-extrabold leading-snug text-white">Ajukan uji dan pantau hasil.</h2>
        <p class="mt-2 text-sm leading-relaxed text-laut-100">Buat akun untuk mengajukan permohonan dan menerima LHP setelah disahkan.</p>
        <ul class="mt-7 space-y-3 border-t border-white/15 pt-5 text-sm text-laut-100">
          <li class="flex items-start gap-2"><x-ikon n="cek" class="mt-0.5 h-4 w-4 shrink-0 text-laut-300" /><span>Data permohonan tersimpan dalam satu akun.</span></li>
          <li class="flex items-start gap-2"><x-ikon n="cek" class="mt-0.5 h-4 w-4 shrink-0 text-laut-300" /><span>Perkembangan pengujian dapat dipantau.</span></li>
          <li class="flex items-start gap-2"><x-ikon n="cek" class="mt-0.5 h-4 w-4 shrink-0 text-laut-300" /><span>LHP tersedia setelah pengesahan selesai.</span></li>
        </ul>
      </div>
    </section>

    <section class="flex min-w-0 items-center justify-center px-5 py-6 sm:px-8" aria-labelledby="judul-daftar">
      <div class="w-full max-w-[480px]">
        <a href="{{ route('home') }}" class="mb-3 inline-flex min-h-[44px] items-center gap-2 rounded-lg px-1 text-sm font-semibold text-kabut-600 no-underline transition-colors duration-200 hover:text-laut-700 hover:no-underline">
          <x-ikon n="kembali" class="h-4 w-4" />
          Kembali
        </a>

        <div class="mb-5 flex items-center gap-3 rounded-xl border border-laut-100 bg-laut-50 p-3 lg:hidden">
          <img src="{{ asset('img/logo-kkp.png') }}" alt="Logo KKP" width="48" height="44" class="h-11 w-auto shrink-0 object-contain">
          <div>
            <p class="text-xs font-bold text-laut-700">SILANTRO</p>
            <p class="mt-0.5 text-sm leading-snug text-kabut-600">Daftar akun dan mulai pengajuan</p>
          </div>
        </div>

        <header>
          <h1 id="judul-daftar" class="text-2xl font-extrabold leading-tight">Buat akun pengguna jasa</h1>
          <p class="mt-1 text-sm leading-relaxed text-kabut-600"><span class="font-semibold text-karang-600" aria-hidden="true">*</span> Data wajib diisi.</p>
        </header>

        <div class="mt-3">@include('partials.flash', ['sembunyikanError' => true])</div>

        <form method="post" action="{{ url('/daftar') }}" class="mt-4 space-y-3" novalidate data-register-form>
          @csrf

          <div data-field>
            <label for="nama" class="label">Nama penanggung jawab <span class="text-karang-600" aria-hidden="true">*</span><span class="sr-only"> wajib</span></label>
            <div class="relative">
              <input id="nama" name="nama" value="{{ old('nama') }}" class="form-input input min-h-[48px] pr-10" autocomplete="name" required @error('nama') aria-invalid="true" aria-describedby="nama-error" data-server-error="{{ $errors->first('nama') }}" @enderror>
              <span data-error-icon="nama" class="field-error-icon hidden" aria-hidden="true"><x-ikon n="peringatan" class="h-5 w-5" /></span>
            </div>
            <p data-field-error="nama" class="auth-field-error hidden"></p>
          </div>

          <div data-field>
            <label for="instansi" class="label">Nama usaha atau instansi <span class="text-karang-600" aria-hidden="true">*</span><span class="sr-only"> wajib</span></label>
            <div class="relative">
              <input id="instansi" name="instansi" value="{{ old('instansi') }}" class="form-input input min-h-[48px] pr-10" autocomplete="organization" required aria-describedby="instansi-help" @error('instansi') aria-invalid="true" data-server-error="{{ $errors->first('instansi') }}" @enderror>
              <span data-error-icon="instansi" class="field-error-icon hidden" aria-hidden="true"><x-ikon n="peringatan" class="h-5 w-5" /></span>
            </div>
            <p id="instansi-help" class="hint">Dicetak pada LHP sebagai pemohon.</p>
            <p data-field-error="instansi" class="auth-field-error hidden"></p>
          </div>

          <div data-field>
            <label for="email" class="label">Email <span class="text-karang-600" aria-hidden="true">*</span><span class="sr-only"> wajib</span></label>
            <div class="relative">
              <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-input input min-h-[48px] pr-10" autocomplete="email" inputmode="email" required @error('email') aria-invalid="true" aria-describedby="email-error" data-server-error="{{ $errors->first('email') }}" @enderror>
              <span data-error-icon="email" class="field-error-icon hidden" aria-hidden="true"><x-ikon n="peringatan" class="h-5 w-5" /></span>
            </div>
            <p data-field-error="email" class="auth-field-error hidden"></p>
          </div>

          <div data-field>
            <label for="telepon" class="label">Telepon atau WhatsApp <span class="text-karang-600" aria-hidden="true">*</span><span class="sr-only"> wajib</span></label>
            <div class="relative flex min-h-[48px] overflow-hidden rounded-lg border border-kabut-300 bg-white focus-within:border-laut-500 focus-within:ring-1 focus-within:ring-laut-500">
              <span class="flex shrink-0 items-center border-r border-kabut-200 bg-kabut-50 px-3 text-sm font-semibold text-tinta" aria-label="Kode negara Indonesia +62">+62</span>
              <input id="telepon" name="telepon_lokal" type="tel" value="{{ old('telepon_lokal') }}" class="input num min-h-[48px] min-w-0 border-0 bg-white px-3 pr-10 shadow-none focus:shadow-none" autocomplete="tel" inputmode="tel" required @error('telepon') aria-invalid="true" aria-describedby="telepon-error" data-server-error="{{ $errors->first('telepon') }}" @enderror>
              <span data-error-icon="telepon" class="field-error-icon !right-3 hidden" aria-hidden="true"><x-ikon n="peringatan" class="h-5 w-5" /></span>
            </div>
            <p data-field-error="telepon" class="auth-field-error hidden"></p>
          </div>

          <div data-field>
            <label for="password" class="label">Kata sandi <span class="text-karang-600" aria-hidden="true">*</span><span class="sr-only"> wajib</span></label>
            <div class="relative">
              <input id="password" name="password" type="password" class="form-input input min-h-[48px] pr-20" autocomplete="new-password" required aria-describedby="password-help" @error('password') aria-invalid="true" data-server-error="{{ $errors->first('password') }}" @enderror>
              <span data-error-icon="password" class="field-error-icon !right-12 hidden" aria-hidden="true"><x-ikon n="peringatan" class="h-5 w-5" /></span>
              <button type="button" class="absolute inset-y-0 right-0 min-h-[48px] min-w-[48px] px-3 text-kabut-600 transition-colors duration-200 hover:text-tinta" data-toggle-password="password" aria-label="Tampilkan kata sandi" aria-controls="password">
                <span data-eye-open><x-ikon n="mata" class="h-5 w-5" /></span>
                <span data-eye-closed class="hidden"><x-ikon n="mata-tutup" class="h-5 w-5" /></span>
              </button>
            </div>
            <p id="password-help" class="hint">Minimal 8 karakter, huruf besar, huruf kecil, dan angka.</p>
            <p data-field-error="password" class="auth-field-error hidden"></p>
          </div>

          <div data-field>
            <label for="password_confirmation" class="label">Ulangi kata sandi <span class="text-karang-600" aria-hidden="true">*</span><span class="sr-only"> wajib</span></label>
            <div class="relative">
              <input id="password_confirmation" name="password_confirmation" type="password" class="form-input input min-h-[48px] pr-20" autocomplete="new-password" required @error('password') aria-invalid="true" data-server-error="Pengulangan kata sandi harus sama dengan kata sandi." @enderror>
              <span data-error-icon="password_confirmation" class="field-error-icon !right-12 hidden" aria-hidden="true"><x-ikon n="peringatan" class="h-5 w-5" /></span>
              <button type="button" class="absolute inset-y-0 right-0 min-h-[48px] min-w-[48px] px-3 text-kabut-600 transition-colors duration-200 hover:text-tinta" data-toggle-password="password_confirmation" aria-label="Tampilkan ulangi kata sandi" aria-controls="password_confirmation">
                <span data-eye-open><x-ikon n="mata" class="h-5 w-5" /></span>
                <span data-eye-closed class="hidden"><x-ikon n="mata-tutup" class="h-5 w-5" /></span>
              </button>
            </div>
            <p data-field-error="password_confirmation" class="auth-field-error hidden"></p>
          </div>

          <button type="submit" class="btn-primary min-h-[48px] w-full py-3" data-submit-button>
            <span data-submit-label>Buat akun</span>
          </button>

          <p class="text-center text-sm text-kabut-600">
            Sudah punya akun?
            <a href="{{ route('login') }}" class="auth-inline-link">Masuk</a>
          </p>
        </form>
      </div>
    </section>
  </div>
</div>
@endsection
