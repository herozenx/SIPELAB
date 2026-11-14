@extends('layouts.guest')
@section('title', 'Layanan uji mutu hasil perikanan')
@section('content')
@php
  $perSampel = $labs->sum('tarif');
  // Ikon ikan kecil yang dipakai berulang di ilustrasi
  $ikan = 'M4 16C8 10.5 14.5 9 19.5 12L27 7.5C25.5 11 24.8 13.5 24.8 16C24.8 18.5 25.5 21 27 24.5L19.5 20C14.5 23 8 21.5 4 16Z';
@endphp

{{-- Hero --}}
<section class="relative overflow-hidden bg-laut-700 text-white">
  <div class="mx-auto max-w-6xl px-4 pb-28 pt-12 sm:px-6 sm:pt-16 lg:grid lg:grid-cols-12 lg:items-center lg:gap-10 lg:pb-36">
    <div class="lg:col-span-6">
      <h1 class="max-w-[18ch] text-[1.9rem] font-extrabold leading-[1.12] tracking-tight text-white sm:text-5xl">Uji mutu hasil perikanan, terpantau sampai LHU terbit.</h1>
      <p class="mt-5 max-w-[56ch] text-base text-laut-100 sm:text-lg">Ajukan permohonan uji secara online, bayar lewat SIMPONI, dan pantau status permohonan Anda dari mana saja. Laporan Hasil Uji diterbitkan digital dan bisa dicek keasliannya oleh siapa pun.</p>
      <div class="mt-8 flex flex-wrap gap-3 max-sm:[&>a]:flex-1">
        <a href="{{ route('register') }}" class="btn min-h-[48px] bg-white px-6 text-laut-700 hover:bg-laut-50">Daftar sebagai pengguna jasa</a>
        <a href="{{ route('login') }}" class="btn min-h-[48px] px-6 text-white ring-1 ring-inset ring-white/40 hover:bg-white/10">Masuk</a>
      </div>
      <ul class="mt-8 flex flex-wrap gap-x-8 gap-y-3 text-sm text-laut-100">
        @foreach (['LHU bernomor dengan kode verifikasi', 'Status permohonan bisa dipantau'] as $poin)
          <li class="flex items-center gap-2">
            <svg viewBox="0 0 24 24" class="h-4 w-4 text-laut-300" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
            {{ $poin }}
          </li>
        @endforeach
      </ul>
    </div>

    <div class="mt-12 lg:col-span-6 lg:mt-0">
      <svg viewBox="0 0 560 400" class="mx-auto h-auto w-full max-w-xl" aria-hidden="true">
        <circle cx="300" cy="200" r="185" fill="#0E4D64"/>
        <g transform="rotate(-4 405 195)">
          <path d="M330 60 H452 L496 104 V316 Q496 328 484 328 H342 Q330 328 330 316 V72 Q330 60 342 60 Z" fill="#F7F9FA"/>
          <path d="M452 60 L496 104 H462 Q452 104 452 94 Z" fill="#CBD5DA"/>
          <rect x="354" y="104" width="118" height="11" rx="5.5" fill="#0B3D50"/>
          <rect x="354" y="130" width="132" height="7" rx="3.5" fill="#CBD5DA"/>
          <rect x="354" y="144" width="108" height="7" rx="3.5" fill="#CBD5DA"/>
          <rect x="354" y="158" width="124" height="7" rx="3.5" fill="#CBD5DA"/>
          <rect x="354" y="182" width="120" height="58" rx="8" fill="#EEF6F8"/>
          <rect x="366" y="196" width="72" height="6" rx="3" fill="#A9D0DC"/>
          <rect x="366" y="212" width="60" height="6" rx="3" fill="#A9D0DC"/>
          <rect x="444" y="196" width="18" height="6" rx="3" fill="#1C6F8C"/>
          <rect x="444" y="212" width="18" height="6" rx="3" fill="#1C6F8C"/>
          <g fill="#0B3D50">
            <rect x="354" y="266" width="3" height="20"/><rect x="361" y="266" width="2" height="20"/>
            <rect x="367" y="266" width="4" height="20"/><rect x="375" y="266" width="2" height="20"/>
            <rect x="381" y="266" width="3" height="20"/><rect x="388" y="266" width="5" height="20"/>
            <rect x="397" y="266" width="2" height="20"/><rect x="403" y="266" width="3" height="20"/>
            <rect x="410" y="266" width="4" height="20"/><rect x="418" y="266" width="2" height="20"/>
            <rect x="424" y="266" width="3" height="20"/>
          </g>
          <rect x="354" y="296" width="74" height="7" rx="3.5" fill="#CBD5DA"/>
          <path d="M458 322 L450 352 L464 344 Z" fill="#082E3D"/>
          <path d="M478 322 L486 352 L472 344 Z" fill="#05202B"/>
          <circle cx="468" cy="300" r="30" fill="#1C6F8C"/>
          <circle cx="468" cy="300" r="22" fill="none" stroke="#A9D0DC" stroke-width="2"/>
          <path d="M456 300 l9 9 l17 -17" fill="none" stroke="#F7F9FA" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
        </g>
        <path d="M232 246 L286 210 C 276 234 276 258 286 282 Z" fill="#3F8EA8"/>
        <path d="M58 248 C 96 206 172 200 224 238 C 232 243 232 253 224 258 C 172 296 96 290 58 248 Z" fill="#74B1C4"/>
        <path d="M116 214 C 138 190 168 188 190 204 C 166 200 140 205 120 217 Z" fill="#3F8EA8"/>
        <path d="M150 262 C 162 274 178 276 190 268 C 176 272 162 270 152 260 Z" fill="#3F8EA8"/>
        <path d="M124 226 Q 136 248 124 272" fill="none" stroke="#3F8EA8" stroke-width="3" stroke-linecap="round"/>
        <path d="M60 254 Q 52 256 46 252" fill="none" stroke="#3F8EA8" stroke-width="3" stroke-linecap="round"/>
        <circle cx="90" cy="240" r="6.5" fill="#05202B"/>
        <circle cx="92.5" cy="237.5" r="2" fill="#F7F9FA"/>
        <g fill="none" stroke="#3F8EA8" stroke-width="2.5">
          <circle cx="34" cy="222" r="5"/><circle cx="22" cy="196" r="8"/><circle cx="40" cy="168" r="4"/>
        </g>
        <g fill="none" stroke="#3F8EA8" stroke-opacity=".55" stroke-width="3" stroke-linecap="round">
          <path d="M44 330 Q 84 316 124 330 T 204 330"/>
          <path d="M84 356 Q 124 342 164 356 T 244 356"/>
        </g>
      </svg>
    </div>
  </div>
  <svg class="absolute bottom-0 left-0 block h-12 w-full text-white sm:h-16" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true">
    <path fill="currentColor" d="M0 48 C240 8 480 0 720 26 C960 52 1200 60 1440 28 V80 H0 Z"/>
  </svg>
</section>

{{-- Lima langkah --}}
@php
  $langkah = [
    ['Ajukan online', 'Isi data sampel, jumlah, asal, dan tujuan distribusi. Lampirkan dokumen pendukung bila ada.', '-rotate-1'],
    ['Serahkan sampel', 'Bawa sampel fisik ke loket. Admin Lab memeriksa kesesuaiannya.', 'rotate-[1.25deg]'],
    ['Bayar lewat SIMPONI', 'Mandiri: bayar di awal sebelum diuji. Eksternal: bayar setelah LHU jadi. Kirim NTPN dan bukti setor dari akun Anda.', '-rotate-[0.5deg]'],
    ['Sampel diproses', 'Sampel diuji oleh laboratorium Balai. Norma waktu '.\App\Models\Setting::get('norma_total_hari').' hari kerja; status bisa dipantau dari akun Anda.', 'rotate-1'],
    ['Terima LHU digital', 'LHU bernomor dengan kode verifikasi. Siapa pun bisa mengecek keasliannya di situs ini.', '-rotate-[1.25deg]'],
  ];
@endphp
<section id="langkah" class="scroll-mt-24 py-14 sm:py-20">
  <div class="mx-auto max-w-6xl px-4 sm:px-6">
    <h2 class="max-w-2xl text-2xl font-extrabold sm:text-3xl">Lima langkah dari pengajuan sampai LHU</h2>
    <p class="mt-3 max-w-2xl text-kabut-600">Prosesnya lurus dari awal sampai akhir, tanpa perlu bolak-balik ke kantor untuk mengurus berkas.</p>

    <ol class="relative mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-5 lg:gap-5 lg:pt-9">
      <li aria-hidden="true" class="absolute top-0 hidden h-px w-full bg-laut-200 lg:block"></li>
      @foreach ($langkah as $i => [$judul, $isi, $miring])
        <li>
          <div class="relative h-full {{ $miring }} rounded-xl border border-kabut-200 bg-white p-5 pt-7 transition-transform duration-200 hover:rotate-0">
            <span aria-hidden="true" class="absolute -top-1 left-1/2 h-3 w-3 -translate-x-1/2 rounded-full border-2 border-laut-200 bg-laut-50"></span>
            <span aria-hidden="true" class="absolute -top-9 left-1/2 hidden h-9 w-px bg-laut-300 lg:block"></span>
            <svg viewBox="0 0 96 80" class="h-20 w-auto" aria-hidden="true">
              @switch($i)
                @case(0)
                  <rect x="12" y="6" width="56" height="68" rx="5" fill="#F7F9FA" stroke="#CBD5DA" stroke-width="1.5"/>
                  <rect x="20" y="15" width="28" height="6" rx="3" fill="#0B3D50"/>
                  <rect x="20" y="28" width="40" height="4" rx="2" fill="#CBD5DA"/>
                  <rect x="20" y="36" width="34" height="4" rx="2" fill="#CBD5DA"/>
                  <rect x="20" y="44" width="38" height="4" rx="2" fill="#CBD5DA"/>
                  <rect x="20" y="55" width="40" height="13" rx="3" fill="#EEF6F8"/>
                  <g transform="translate(24 57.8) scale(0.75)"><path d="{{ $ikan }}" fill="#1C6F8C"/><circle cx="9.5" cy="15" r="1.6" fill="#EEF6F8"/></g>
                  <g transform="rotate(45 78 28)">
                    <rect x="74.5" y="4" width="7" height="6" rx="2" fill="#CBD5DA"/>
                    <rect x="74.5" y="10" width="7" height="22" rx="2" fill="#1C6F8C"/>
                    <path d="M74.5 32h7l-3.5 8z" fill="#CBD5DA"/>
                  </g>
                  @break
                @case(1)
                  <rect x="22" y="12" width="52" height="10" rx="4" fill="#74B1C4"/>
                  <rect x="26" y="20" width="44" height="52" rx="7" fill="#F7F9FA" stroke="#CBD5DA" stroke-width="1.5"/>
                  <path d="M28 45c4-3 8-3 12 0s8 3 12 0 8-3 12 0c2 1.2 4 1.2 6 .5V66q0 4-4 4H32q-4 0-4-4Z" fill="#A9D0DC"/>
                  <g transform="translate(34 47) scale(0.85)"><path d="{{ $ikan }}" fill="#1C6F8C"/><circle cx="9.5" cy="15" r="1.6" fill="#A9D0DC"/></g>
                  <rect x="50" y="26" width="16" height="10" rx="2" fill="#F7F9FA" stroke="#CBD5DA" stroke-width="1.5"/>
                  <g fill="none" stroke="#3F8EA8" stroke-width="2"><circle cx="18" cy="36" r="3"/><circle cx="13" cy="27" r="2"/></g>
                  @break
                @case(2)
                  <g transform="rotate(-6 40 56)">
                    <rect x="12" y="42" width="54" height="28" rx="4" fill="#1C6F8C"/>
                    <circle cx="39" cy="56" r="10" fill="none" stroke="#A9D0DC" stroke-width="2"/>
                    <g transform="translate(31 51.5) scale(0.62)"><path d="{{ $ikan }}" fill="#A9D0DC"/><circle cx="9.5" cy="15" r="1.8" fill="#1C6F8C"/></g>
                    <rect x="17" y="47" width="8" height="5" rx="1" fill="#3F8EA8"/>
                    <rect x="53" y="60" width="8" height="5" rx="1" fill="#3F8EA8"/>
                  </g>
                  <g transform="translate(60 4)">
                    <rect width="30" height="30" rx="4" fill="#F7F9FA" stroke="#CBD5DA" stroke-width="1.5"/>
                    <rect x="5" y="5" width="8" height="8" fill="#0B3D50"/><rect x="17" y="5" width="8" height="8" fill="#0B3D50"/>
                    <rect x="5" y="17" width="8" height="8" fill="#0B3D50"/><rect x="18" y="18" width="3.5" height="3.5" fill="#0B3D50"/>
                    <rect x="23" y="18" width="3.5" height="3.5" fill="#1C6F8C"/><rect x="18" y="23" width="3.5" height="3.5" fill="#1C6F8C"/>
                    <rect x="23" y="23" width="3.5" height="3.5" fill="#0B3D50"/>
                  </g>
                  @break
                @case(3)
                  <path d="M40 14h16v12l14 30c3 7-2 12-8 12H34c-6 0-11-5-8-12l14-30Z" fill="#F7F9FA" stroke="#CBD5DA" stroke-width="1.5"/>
                  <path d="M31 46l-5 10c-3 7 2 12 8 12h28c6 0 11-5 8-12l-5-10c-5.3 3.3-10.7 5-17 5s-11.7-1.7-17-5Z" fill="#74B1C4"/>
                  <g transform="translate(39 49) scale(0.75)"><path d="{{ $ikan }}" fill="#0E4D64"/><circle cx="9.5" cy="15" r="1.7" fill="#F7F9FA"/></g>
                  <g fill="none" stroke="#3F8EA8" stroke-width="2"><circle cx="44" cy="42" r="2"/><circle cx="52" cy="37" r="2.5"/></g>
                  <rect x="37" y="10" width="22" height="5" rx="2.5" fill="#74B1C4"/>
                  @break
                @default
                  <path d="M26 40L38 30c-3 7-3 13 0 20Z" fill="#3F8EA8"/>
                  <ellipse cx="15" cy="40" rx="11" ry="7.5" fill="#74B1C4"/>
                  <circle cx="9" cy="38.5" r="1.8" fill="#05202B"/>
                  <path d="M9 46q4 3 9 1" stroke="#3F8EA8" stroke-width="1.5" fill="none" stroke-linecap="round"/>
                  <g transform="rotate(-3 54 40)">
                    <rect x="30" y="8" width="48" height="64" rx="5" fill="#F7F9FA" stroke="#CBD5DA" stroke-width="1.5"/>
                    <rect x="37" y="16" width="24" height="5" rx="2.5" fill="#0B3D50"/>
                    <rect x="37" y="26" width="32" height="4" rx="2" fill="#CBD5DA"/>
                    <rect x="37" y="34" width="28" height="4" rx="2" fill="#CBD5DA"/>
                    <rect x="37" y="42" width="30" height="4" rx="2" fill="#CBD5DA"/>
                    <circle cx="64" cy="58" r="11" fill="#1C6F8C"/>
                    <path d="M59.5 58l3.5 3.5 6-6.5" stroke="#F7F9FA" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                  </g>
              @endswitch
            </svg>
            <h3 class="mt-4 font-bold"><span class="num mr-1.5 text-laut-500">{{ $i + 1 }}.</span>{{ $judul }}</h3>
            <p class="mt-1.5 text-sm text-kabut-600">{{ $isi }}</p>
          </div>
        </li>
      @endforeach
    </ol>
  </div>
</section>

{{-- Tarif + cek LHU --}}
<section id="tarif" class="scroll-mt-24 border-t border-kabut-200 bg-kabut-50 py-14 sm:py-20">
  <div class="mx-auto grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16">
    <div>
      <h2 class="text-2xl font-extrabold sm:text-3xl">Tarif pengujian</h2>
      <p class="mt-3 max-w-md text-kabut-600">Tagihan dihitung otomatis dari tarif setiap laboratorium dikalikan jumlah sampel, ditambah biaya administrasi.</p>

      <div class="relative mt-8 max-w-md -rotate-1 rounded-sm bg-white p-6 shadow-[0_1px_2px_rgba(11,61,80,0.10)] sm:p-7">
        <div class="flex items-center justify-between border-b border-dashed border-kabut-300 pb-4">
          <img src="{{ asset('favicon.svg') }}" alt="" class="h-9 w-9">
          <div class="text-right">
            <p class="text-sm font-bold leading-none">Nota tarif uji</p>
            <p class="mt-1 text-xs text-kabut-500">SIPELAB · BPPMHKP Surabaya II</p>
          </div>
        </div>

        <div class="mt-2">
          @foreach ($labs as $l)
            @php $nm = strtolower($l->nama); @endphp
            <div class="flex items-center gap-3 py-3">
              <svg viewBox="0 0 24 24" class="h-6 w-6 shrink-0" aria-hidden="true">
                @if (str_contains($nm, 'nekropsi'))
                  <g transform="translate(1 8) scale(0.55)"><path d="{{ $ikan }}" fill="#1C6F8C"/><circle cx="9.5" cy="15" r="1.8" fill="#D5E8EE"/></g>
                  <circle cx="17.5" cy="7.5" r="4" fill="none" stroke="#0E4D64" stroke-width="1.8"/>
                  <path d="M20.5 10.5l2.5 2.5" stroke="#0E4D64" stroke-width="1.8" stroke-linecap="round"/>
                @elseif (str_contains($nm, 'mikro'))
                  <ellipse cx="12" cy="12.5" rx="9" ry="4.5" fill="#D5E8EE" stroke="#CBD5DA" stroke-width="1.5"/>
                  <ellipse cx="12" cy="14" rx="9" ry="4.5" fill="#F7F9FA" stroke="#CBD5DA" stroke-width="1.5"/>
                  <g fill="#1C6F8C"><circle cx="9" cy="14" r="1.1"/><circle cx="14" cy="15.5" r="1.1"/><circle cx="12" cy="12.8" r="1.1"/></g>
                @else
                  <rect x="8.5" y="3" width="7" height="15" rx="3.5" fill="#F7F9FA" stroke="#CBD5DA" stroke-width="1.5"/>
                  <path d="M8.5 12.5v2a3.5 3.5 0 0 0 7 0v-2c-1.2.8-2.3 1.2-3.5 1.2s-2.3-.4-3.5-1.2Z" fill="#74B1C4"/>
                  <circle cx="13" cy="16" r="1" fill="#0E4D64"/>
                @endif
              </svg>
              <span>{{ $l->nama }}</span>
              <span aria-hidden="true" class="mx-1 h-0 flex-1 border-t-2 border-dotted border-kabut-300"></span>
              <span class="num">{{ rupiah($l->tarif) }}</span>
            </div>
          @endforeach

          <div class="mt-1 flex items-end justify-between gap-4 border-t-2 border-laut-700/10 pt-4">
            <span class="font-bold leading-snug">Total per sampel</span>
            <span class="num whitespace-nowrap text-xl font-extrabold text-laut-600">{{ rupiah($perSampel) }}</span>
          </div>
          <div class="mt-2 flex items-center gap-3 border-t border-dashed border-kabut-300 py-3 text-sm text-kabut-500">
            <span>Biaya administrasi per permohonan</span>
            <span aria-hidden="true" class="mx-1 h-0 flex-1 border-t-2 border-dotted border-kabut-300"></span>
            <span class="num">{{ rupiah($biayaAdmin) }}</span>
          </div>
        </div>

        <div class="mt-3 border-t border-dashed border-kabut-300 pt-5 text-center">
          <svg viewBox="0 0 120 28" class="mx-auto h-7 w-32" fill="#0B3D50" aria-hidden="true">
            <rect x="0" y="4" width="3" height="20"/><rect x="6" y="4" width="2" height="20"/><rect x="11" y="4" width="4" height="20"/><rect x="18" y="4" width="2" height="20"/><rect x="23" y="4" width="3" height="20"/><rect x="29" y="4" width="5" height="20"/><rect x="37" y="4" width="2" height="20"/><rect x="42" y="4" width="3" height="20"/><rect x="48" y="4" width="4" height="20"/><rect x="55" y="4" width="2" height="20"/><rect x="60" y="4" width="3" height="20"/><rect x="66" y="4" width="5" height="20"/><rect x="74" y="4" width="2" height="20"/><rect x="79" y="4" width="3" height="20"/><rect x="85" y="4" width="4" height="20"/><rect x="92" y="4" width="2" height="20"/><rect x="97" y="4" width="3" height="20"/><rect x="103" y="4" width="5" height="20"/><rect x="111" y="4" width="2" height="20"/><rect x="116" y="4" width="3" height="20"/>
          </svg>
          <p class="mt-3 text-sm text-kabut-500">Tarif mengacu pada ketentuan Balai yang berlaku.</p>
        </div>
      </div>
    </div>

    <div id="cek-lhu" class="scroll-mt-24 self-start rounded-2xl border border-kabut-200 bg-white p-6 sm:p-8">
      <div class="flex items-center gap-5">
        <svg viewBox="0 0 96 56" class="h-14 w-auto shrink-0" aria-hidden="true">
          <g transform="rotate(-4 36 28)">
            <rect x="14" y="4" width="44" height="48" rx="4" fill="#F7F9FA" stroke="#CBD5DA" stroke-width="1.5"/>
            <rect x="20" y="11" width="20" height="4.5" rx="2.25" fill="#0B3D50"/>
            <rect x="20" y="20" width="28" height="3.5" rx="1.75" fill="#CBD5DA"/>
            <rect x="20" y="27" width="24" height="3.5" rx="1.75" fill="#CBD5DA"/>
            <rect x="20" y="34" width="26" height="3.5" rx="1.75" fill="#CBD5DA"/>
            <circle cx="49" cy="43" r="7" fill="#1C6F8C"/>
            <path d="M46 43l2.4 2.4 4.2-4.8" stroke="#F7F9FA" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
          </g>
          <circle cx="70" cy="28" r="13" fill="#D5E8EE" stroke="#0E4D64" stroke-width="3"/>
          <path d="M63.5 28l4.5 4.5 8-9" stroke="#0E4D64" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
          <path d="M80.5 38.5l11 11" stroke="#0E4D64" stroke-width="4.5" stroke-linecap="round"/>
        </svg>
        <h2 class="text-xl font-extrabold sm:text-2xl">Cek keaslian LHU</h2>
      </div>
      <p class="mt-4 text-kabut-600">Masukkan kode verifikasi yang tercetak di LHU untuk memastikan dokumen diterbitkan oleh Balai. Data pemohon tidak ditampilkan.</p>

      <form action="{{ route('verifikasi') }}" method="get" class="mt-6">
        <label for="kode" class="label">Kode verifikasi</label>
        <input id="kode" name="kode" class="input num min-h-[48px] uppercase tracking-[0.08em] placeholder:normal-case placeholder:tracking-normal" placeholder="Contoh: 3F9A0C12B7E4" maxlength="12" autocomplete="off" spellcheck="false" required aria-describedby="kode-hint">
        <p id="kode-hint" class="hint">12 karakter: angka 0–9 dan huruf A–F.</p>
        <button class="btn-primary mt-5 min-h-[48px] w-full px-6 sm:w-auto"><x-ikon n="perisai" class="h-4 w-4" />Cek LHU</button>
      </form>
    </div>
  </div>
</section>

{{-- Pusat bantuan --}}
@php
  $wa = wa_link('Halo Balai, saya ingin bertanya tentang layanan uji SIPELAB.');
  $bantuan = array_values(array_filter([
    ['buku', 'Panduan penggunaan', 'Langkah demi langkah dari daftar akun sampai LHU, per bagian.', route('panduan'), false],
    ['chat', 'Chat petugas', auth()->check() ? 'Tanya langsung ke admin atau operator lab yang sedang online.' : 'Masuk dulu, lalu tanya langsung ke admin atau operator lab yang sedang online.', auth()->check() ? route('chat') : route('login'), false],
    $wa ? ['chat', 'WhatsApp Center', 'Hubungi Balai lewat WhatsApp pada jam layanan.', $wa, true] : null,
    ['perisai', 'Cek keaslian LHU', 'Sudah menerima LHU? Pastikan dokumen Anda sah sebelum dipakai untuk keperluan administrasi.', route('verifikasi'), false],
  ]));
@endphp
<section id="bantuan" class="scroll-mt-24 py-14 sm:py-20">
  <div class="mx-auto max-w-6xl px-4 sm:px-6">
    <h2 class="text-2xl font-extrabold sm:text-3xl">Pusat bantuan</h2>
    <p class="mt-3 max-w-2xl text-kabut-600">Masih ada yang mengganjal? Pilih jalur bantuan yang paling nyaman untuk Anda.</p>

    <div class="mt-12 grid gap-12 lg:grid-cols-[1fr_1.2fr] lg:gap-16">
      <div>
        <svg viewBox="0 0 320 220" class="w-full max-w-sm" aria-hidden="true">
          <g transform="rotate(-5 210 110)">
            <rect x="150" y="26" width="118" height="156" rx="8" fill="#F7F9FA" stroke="#CBD5DA" stroke-width="2"/>
            <rect x="164" y="44" width="56" height="9" rx="4.5" fill="#0B3D50"/>
            <rect x="164" y="62" width="76" height="6" rx="3" fill="#CBD5DA"/>
            <rect x="164" y="74" width="66" height="6" rx="3" fill="#CBD5DA"/>
            <rect x="164" y="86" width="72" height="6" rx="3" fill="#CBD5DA"/>
            <rect x="164" y="102" width="60" height="34" rx="5" fill="#EEF6F8"/>
            <g transform="translate(172 110) scale(0.85)"><path d="{{ $ikan }}" fill="#1C6F8C"/><circle cx="9.5" cy="15" r="1.7" fill="#EEF6F8"/></g>
            <circle cx="238" cy="154" r="16" fill="#1C6F8C"/>
            <path d="M231 154l5 5 9-10" stroke="#F7F9FA" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
          </g>
          <rect x="14" y="34" width="88" height="56" rx="14" fill="#0E4D64"/>
          <path d="M34 90l-6 16 18-16Z" fill="#0E4D64"/>
          <g transform="translate(38 48) scale(1.1)"><path d="{{ $ikan }}" fill="#A9D0DC"/><circle cx="9.5" cy="15" r="1.7" fill="#0E4D64"/></g>
          <rect x="20" y="122" width="64" height="34" rx="10" fill="#FFFFFF" stroke="#CBD5DA" stroke-width="2"/>
          <g fill="#3F8EA8"><circle cx="38" cy="139" r="3"/><circle cx="50" cy="139" r="3"/><circle cx="62" cy="139" r="3"/></g>
          <circle cx="272" cy="176" r="24" fill="#D5E8EE" stroke="#0E4D64" stroke-width="5"/>
          <path d="M258 176l10 10 17-19" stroke="#0E4D64" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
          <path d="M290 194l20 20" stroke="#0E4D64" stroke-width="8" stroke-linecap="round"/>
          <g fill="none" stroke="#CBD5DA" stroke-width="3" stroke-linecap="round"><path d="M20 196q22-10 44 0t44 0"/><path d="M60 212q22-10 44 0t44 0"/></g>
          <g fill="none" stroke="#74B1C4" stroke-width="2.5"><circle cx="120" cy="20" r="5"/><circle cx="136" cy="12" r="3"/></g>
        </svg>
      </div>

      <div>
        @foreach ($bantuan as $k => [$ikon, $judul, $isi, $url, $luar])
          @if ($k > 0)<div class="h-px bg-kabut-200"></div>@endif
          <a href="{{ $url }}" @if($luar) target="_blank" rel="noopener" @endif class="group -mx-4 flex items-start gap-5 rounded-xl p-4 text-tinta no-underline hover:bg-kabut-50 hover:no-underline sm:mx-0 sm:rounded-none sm:px-0 sm:py-6 sm:hover:bg-transparent">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-laut-50 text-laut-600"><x-ikon :n="$ikon" class="h-6 w-6" /></span>
            <span class="flex-1">
              <span class="block font-bold">{{ $judul }}</span>
              <span class="mt-1 block text-sm text-kabut-600">{{ $isi }}</span>
            </span>
            <span aria-hidden="true" class="mt-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-kabut-300 text-laut-600 transition-colors group-hover:border-laut-600 group-hover:bg-laut-600 group-hover:text-white">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </span>
          </a>
        @endforeach
      </div>
    </div>
  </div>
</section>
@endsection
