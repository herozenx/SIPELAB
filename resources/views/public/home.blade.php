@extends('layouts.guest')
@section('title', 'Layanan uji mutu hasil perikanan')
@section('content')
@php $perSampel = $labs->sum('tarif'); @endphp

<section class="bg-laut-700 text-white">
  <div class="mx-auto max-w-6xl px-4 pb-14 pt-10 sm:px-6 sm:pt-12 lg:pb-20 lg:pt-16">
    <h1 class="max-w-[18ch] text-[1.9rem] font-extrabold leading-[1.12] tracking-tight text-white sm:text-5xl">Uji mutu hasil perikanan, terpantau sampai LHU terbit.</h1>
    <p class="mt-5 max-w-[56ch] text-base text-laut-100 sm:text-lg">Ajukan permohonan uji secara online, bayar lewat SIMPONI, dan pantau status permohonan Anda dari mana saja. Laporan Hasil Uji diterbitkan digital dan bisa dicek keasliannya oleh siapa pun.</p>
    <div class="mt-8 flex flex-wrap gap-3 max-sm:[&>a]:flex-1">
      <a href="{{ route('register') }}" class="btn bg-white py-3 text-laut-700 hover:bg-laut-50">Daftar sebagai pengguna jasa</a>
      <a href="{{ route('login') }}" class="btn py-3 text-white ring-1 ring-inset ring-white/40 hover:bg-white/10">Masuk</a>
    </div>
  </div>
</section>

<section class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:py-20">
  <h2 class="text-2xl font-extrabold sm:text-3xl">Lima langkah dari pengajuan sampai LHU</h2>
  <ol class="mt-9 grid gap-x-8 gap-y-8 sm:grid-cols-2 lg:grid-cols-5">
    @foreach ([
      ['Ajukan online', 'Isi data sampel, jumlah, asal, dan tujuan distribusi. Lampirkan dokumen pendukung bila ada.'],
      ['Serahkan sampel', 'Bawa sampel fisik ke loket. Admin Lab memeriksa kesesuaiannya.'],
      ['Bayar lewat SIMPONI', 'Mandiri: bayar di awal sebelum diuji. Eksternal: bayar setelah LHU jadi. Kirim NTPN dan bukti setor dari akun Anda.'],
      ['Sampel diproses', 'Sampel diuji oleh laboratorium Balai. Norma waktu '.\App\Models\Setting::get('norma_total_hari').' hari kerja; status bisa dipantau dari akun Anda.'],
      ['Terima LHU digital', 'LHU bernomor dengan kode verifikasi. Siapa pun bisa mengecek keasliannya di situs ini.'],
    ] as $i => [$judul, $isi])
      <li class="border-t-[3px] border-laut-600 pt-4">
        <span class="num text-sm font-bold text-laut-500">{{ $i + 1 }}</span>
        <h3 class="mt-1 font-bold">{{ $judul }}</h3>
        <p class="mt-2 text-sm text-kabut-600">{{ $isi }}</p>
      </li>
    @endforeach
  </ol>
</section>

<section class="border-t border-kabut-200 bg-kabut-50">
  <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-2 lg:py-16">
    <div>
      <h2 class="text-2xl font-extrabold">Tarif pengujian</h2>
      <p class="mt-2 max-w-[48ch] text-sm text-kabut-600">Tagihan dihitung otomatis dari tarif setiap laboratorium dikalikan jumlah sampel, ditambah biaya administrasi.</p>
      <div class="panel mt-6 overflow-hidden">
        <table class="tabel">
          <thead><tr><th>Laboratorium</th><th class="text-right">Tarif per sampel</th></tr></thead>
          <tbody>
            @foreach ($labs as $l)
              <tr><td>{{ $l->nama }}</td><td class="num text-right">{{ rupiah($l->tarif) }}</td></tr>
            @endforeach
            <tr><td class="font-semibold">Total per sampel</td><td class="num text-right font-semibold">{{ rupiah($perSampel) }}</td></tr>
            <tr><td class="text-kabut-500">Biaya administrasi per permohonan</td><td class="num text-right text-kabut-500">{{ rupiah($biayaAdmin) }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div>
      <h2 class="text-2xl font-extrabold">Cek keaslian LHU</h2>
      <p class="mt-2 max-w-[48ch] text-sm text-kabut-600">Masukkan 12 karakter kode verifikasi yang tercetak di LHU. Data pemohon tidak ditampilkan.</p>
      <form action="{{ route('verifikasi') }}" method="get" class="mt-6 flex flex-col gap-3 sm:flex-row">
        <label for="kode" class="sr-only">Kode verifikasi</label>
        <input id="kode" name="kode" class="input num uppercase placeholder:normal-case sm:max-w-xs" placeholder="Contoh: 3F9A0C12B7E4" maxlength="12" autocomplete="off" required>
        <button class="btn-primary"><x-ikon n="perisai" class="h-4 w-4" />Cek LHU</button>
      </form>
    </div>
  </div>
</section>

{{-- Pusat bantuan --}}
@php $wa = wa_link('Halo Balai, saya ingin bertanya tentang layanan uji SIPELAB.'); @endphp
<section id="bantuan" class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:py-16">
  <h2 class="text-2xl font-extrabold">Pusat bantuan</h2>
  <p class="mt-2 max-w-[56ch] text-sm text-kabut-600">Panduan lengkap, chat langsung dengan petugas, dan kontak Balai.</p>
  <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach (array_values(array_filter([
      ['buku', 'Panduan penggunaan', 'Langkah demi langkah dari daftar akun sampai LHU, per bagian.', route('panduan'), 'Baca panduan', false],
      ['chat', 'Chat petugas', 'Tanya langsung ke admin atau operator lab yang sedang online.', auth()->check() ? route('chat') : route('login'), auth()->check() ? 'Buka chat' : 'Masuk untuk chat', false],
      $wa ? ['chat', 'WhatsApp Center', 'Hubungi Balai lewat WhatsApp pada jam layanan.', $wa, 'Chat WhatsApp', true] : null,
      ['perisai', 'Cek keaslian LHU', 'Pastikan LHU yang Anda terima diterbitkan Balai.', route('verifikasi'), 'Cek LHU', false],
    ])) as [$ikon, $judul, $isi, $url, $aksi, $luar])
      <a href="{{ $url }}" @if($luar) target="_blank" rel="noopener" @endif class="panel group flex flex-col p-5 text-tinta no-underline hover:no-underline hover:shadow-[0_0_0_2px_theme(colors.laut.400)]">
        <span class="grid h-10 w-10 place-items-center rounded-lg bg-laut-50 text-laut-600"><x-ikon :n="$ikon" class="h-5 w-5" /></span>
        <span class="mt-3 font-bold">{{ $judul }}</span>
        <span class="mt-1 flex-1 text-sm text-kabut-600">{{ $isi }}</span>
        <span class="mt-4 text-sm font-semibold text-laut-600 group-hover:underline">{{ $aksi }} →</span>
      </a>
    @endforeach
  </div>
</section>
@endsection
