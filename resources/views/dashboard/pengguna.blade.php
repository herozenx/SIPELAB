@extends('layouts.app')
@section('title', 'Beranda')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1>Halo, {{ $u->nama }}</h1>
    <p class="mt-1 text-kabut-600">{{ $u->instansi }}</p>
  </div>
  <a href="{{ route('permohonan.create') }}" class="btn-primary"><x-ikon n="tambah" class="h-4 w-4" />Ajukan uji baru</a>
</div>

{{-- Ajakan membaca panduan; bisa ditutup (diingat di browser) --}}
<div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-5 py-4 shadow-[0_0_0_1px_theme(colors.kabut.200)]" data-dismissable="panduan-v1">
  <span class="flex items-start gap-3">
    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-laut-50 text-laut-600"><x-ikon n="buku" class="h-5 w-5" /></span>
    <span><span class="block font-bold">Baru pertama kali memakai SIPELAB?</span><span class="text-sm text-kabut-600">Ikuti panduan langkah demi langkah: dari pengajuan, pembayaran, sampai mengunduh LHU. Ada pertanyaan? Chat langsung dengan petugas.</span></span>
  </span>
  <span class="flex flex-wrap gap-2">
    <a href="{{ route('panduan') }}" class="btn-primary btn-sm">Baca panduan</a>
    <a href="{{ route('chat') }}" class="btn-ghost btn-sm"><x-ikon n="chat" class="h-4 w-4" />Chat petugas</a>
    <button type="button" class="btn-ghost btn-sm" data-dismiss aria-label="Tutup ajakan panduan"><x-ikon n="silang" class="h-4 w-4" /></button>
  </span>
</div>

@foreach ($perluTindakan as $p)
  <a href="{{ route('permohonan.show', $p) }}" class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-laut-600 px-5 py-4 text-white no-underline hover:bg-laut-700 hover:no-underline">
    @if ($p->eksternal())
      <span><span class="block font-bold">LHU {{ $p->kode }} sudah jadi: lunasi untuk mengambil</span><span class="text-sm text-laut-100">Bayar {{ rupiah($p->tagihan) }} lewat SIMPONI dengan kode billing <span class="num font-semibold text-white">{{ $p->kode_billing }}</span>, lalu kirim NTPN. LHU terbuka setelah lunas.</span></span>
    @else
      <span><span class="block font-bold">Bayar tagihan {{ $p->kode }}</span><span class="text-sm text-laut-100">{{ rupiah($p->tagihan) }} lewat SIMPONI dengan kode billing <span class="num font-semibold text-white">{{ $p->kode_billing }}</span>, lalu kirim NTPN.</span></span>
    @endif
    <span class="btn bg-white text-laut-700">Kirim bukti bayar</span>
  </a>
@endforeach

@if ($rows->isEmpty())
  <section class="panel mt-6 px-6 py-14 text-center">
    <h2>Belum ada permohonan</h2>
    <p class="mx-auto mt-2 max-w-[46ch] text-sm text-kabut-600">Ajukan permohonan uji, lalu bawa sampel ke loket Balai. Status sampel Anda akan tampil di sini dan diperbarui otomatis.</p>
    <div class="mt-5 flex flex-wrap justify-center gap-2">
      <a href="{{ route('permohonan.create') }}" class="btn-primary">Ajukan uji pertama</a>
      <a href="{{ route('panduan') }}" class="btn-ghost">Lihat panduan</a>
    </div>
  </section>
@else
  <section class="panel mt-6 overflow-hidden">
    <div class="panel-head"><h2>Permohonan terbaru</h2><a href="{{ route('permohonan.index') }}" class="text-sm font-semibold">Lihat semua</a></div>
    @include('partials.tabel-permohonan', ['rows' => $rows, 'labs' => $labs, 'pemohon' => false])
  </section>
@endif
@endsection
