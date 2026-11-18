@extends('layouts.app')
@section('title', 'Beranda')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1>Halo, {{ auth()->user()->nama }}</h1>
    <p class="mt-1 text-kabut-600">{{ $antrean->count() }} sampel menunggu pemeriksaan isian operator dan file LHP.</p>
  </div>
  <a href="{{ route('permohonan.index') }}" class="btn-ghost">Semua sampel</a>
</div>

<section class="panel mt-6 overflow-hidden">
  <div class="panel-head"><h2>Perlu diperiksa</h2><span class="num rounded-full bg-kabut-100 px-2 py-0.5 text-xs font-bold text-kabut-600">{{ $antrean->count() }}</span></div>
  @if ($antrean->isEmpty())
    <p class="px-5 py-10 text-center text-sm text-kabut-500">Tidak ada yang menunggu pemeriksaan.</p>
  @else
    <div class="overflow-x-auto">
      <table class="tabel tabel-hp">
        <thead><tr><th>Kode sampel</th><th>Pelanggan</th><th>Sampel</th><th>Layanan</th><th class="text-right">Masuk antrean</th></tr></thead>
        <tbody>
          @foreach ($antrean as $r)
            <tr data-href="{{ route('permohonan.show', $r) }}">
              <td class="whitespace-nowrap"><a href="{{ route('permohonan.show', $r) }}" class="kode no-underline hover:underline">{{ $r->kode_sampel ?: $r->kode }}</a></td>
              <td data-label="Pelanggan">{{ $r->pelanggan ?: ($r->pemohon?->instansi ?: $r->pemohon?->nama) }}</td>
              <td data-label="Sampel"><span>{{ $r->sampel->pluck('jenis_sampel')->join(', ') }}<span class="block text-xs text-kabut-500">{{ $r->sampel->count() }} sampel</span></span></td>
              <td class="whitespace-nowrap" data-label="Layanan">{{ $r->jenis_bayar?->label() }}</td>
              <td class="whitespace-nowrap text-right text-sm text-kabut-500" data-label="Masuk" title="{{ tanggal($r->updated_at) }}">{{ $r->updated_at->locale('id')->diffForHumans() }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
  <section class="panel">
    <div class="panel-head"><h2>Sedang diperbaiki lab</h2></div>
    <ul class="divide-y divide-kabut-100">
      @forelse ($diperbaiki as $r)
        <li><a href="{{ route('permohonan.show', $r) }}" class="block px-5 py-3 text-tinta no-underline hover:bg-kabut-50 hover:no-underline">
          <span class="flex items-center justify-between gap-2"><span class="kode text-sm">{{ $r->kode_sampel }}</span><span class="badge-lab">Lab {{ $r->labSaatIni?->nama }}</span></span>
          <span class="mt-0.5 block text-xs text-kabut-500">{{ $r->lhu?->catatan_cek }}</span>
        </a></li>
      @empty
        <li class="px-5 py-8 text-center text-sm text-kabut-500">Tidak ada yang sedang dikembalikan.</li>
      @endforelse
    </ul>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Terakhir Anda periksa</h2></div>
    <ul class="divide-y divide-kabut-100">
      @forelse ($riwayat as $l)
        <li><a href="{{ route('permohonan.show', $l->permohonan_id) }}" class="flex items-center justify-between gap-2 px-5 py-3 text-tinta no-underline hover:bg-kabut-50 hover:no-underline">
          <span><span class="kode text-sm">{{ $l->permohonan?->kode_sampel }}</span><span class="block text-xs text-kabut-500">{{ tanggal($l->cek_at) }}</span></span>
          <x-status :p="$l->permohonan" />
        </a></li>
      @empty
        <li class="px-5 py-8 text-center text-sm text-kabut-500">Belum ada.</li>
      @endforelse
    </ul>
  </section>
</div>
@endsection
