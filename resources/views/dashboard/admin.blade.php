@extends('layouts.app')
@section('title', 'Beranda')
@section('content')
@php
  $papan = array_merge(
    [['Perlu verifikasi', $verifikasi->count(), 'wait'], ['Menunggu bayar', $menungguBayar, 'info'], ['Cek pembayaran', $bayar->count(), 'wait']],
    $labs->map(fn ($l) => ['Lab '.$l->nama, (int) ($diLab[$l->id] ?? 0), 'lab'])->all(),
    [['Validasi LHU', $validasi->count(), 'wait'], ['Lewat norma waktu', $lewatNorma->count(), 'bad']]
  );
  $antrean = [
    ['Perlu verifikasi sampel', 'Periksa sampel fisik di loket, lalu terbitkan kode billing atau tolak.', $verifikasi],
    ['Cek pembayaran SIMPONI', 'Cocokkan NTPN dengan dana masuk di SIMPONI sebelum mengonfirmasi.', $bayar],
    ['Validasi dan terbitkan LHU', 'Seluruh lab sudah selesai. Periksa rekap hasil, lalu terbitkan LHU.', $validasi],
  ];
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1>Halo, {{ auth()->user()->nama }}</h1>
    <p class="mt-1 text-kabut-600">{{ $verifikasi->count() + $bayar->count() + $validasi->count() }} permohonan menunggu tindakan Anda.</p>
  </div>
  <a href="{{ route('permohonan.index') }}" class="btn-ghost">Semua permohonan</a>
</div>

{{-- Papan lintasan: berapa sampel di setiap stasiun --}}
<section class="panel mt-6 overflow-hidden" aria-label="Posisi seluruh permohonan aktif">
  {{-- HP: 2 kolom, tablet: 4 kolom, layar lebar: satu baris --}}
  <ol class="grid grid-cols-2 gap-px bg-kabut-200 sm:grid-cols-4 lg:flex">
    @foreach ($papan as $i => [$label, $n, $tone])
      <li class="relative flex-1 bg-white px-4 py-4">
        <p class="num text-2xl font-extrabold {{ $n ? match ($tone) { 'lab' => 'text-laut-600', 'wait' => 'text-surya-600', 'bad' => 'text-karang-600', default => 'text-tinta' } : 'text-kabut-300' }}">{{ $n }}</p>
        <p class="mt-0.5 text-xs font-semibold text-kabut-500">{{ $label }}</p>
      </li>
    @endforeach
  </ol>
</section>

@if ($lewatNorma->isNotEmpty())
  <section class="panel mt-6 overflow-hidden border-l-4 border-karang-600">
    <div class="panel-head"><h2>Sampel melewati norma waktu</h2><a href="{{ route('tim.index') }}" class="text-sm font-semibold">Atur tim operator</a></div>
    <div class="overflow-x-auto">
    <table class="tabel tabel-hp">
      <thead><tr><th>Kode</th><th>Lab</th><th>Ditangani</th><th>Batas</th><th>Keterlambatan</th></tr></thead>
      <tbody>
        @foreach ($lewatNorma as $t)
          @php [$tTeks] = $t->norma(); @endphp
          <tr data-href="{{ route('permohonan.show', $t->permohonan_id) }}">
            <td><a href="{{ route('permohonan.show', $t->permohonan_id) }}" class="kode no-underline hover:underline">{{ $t->permohonan->kode }}</a><span class="block text-xs text-kabut-500">{{ $t->permohonan->nama_sampel }}</span></td>
            <td class="whitespace-nowrap" data-label="Lab">Lab {{ $t->lab->nama }}</td>
            <td class="text-sm" data-label="Ditangani">{{ $t->operator->pluck('nama')->join(', ') ?: 'Belum ada' }}</td>
            <td class="num whitespace-nowrap text-kabut-600" data-label="Batas">{{ tanggal($t->batas_at, false) }}</td>
            <td data-label="Keterlambatan"><span class="badge-bad">{{ $tTeks }}</span></td>
          </tr>
        @endforeach
      </tbody>
    </table>
    </div>
  </section>
@endif

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
  @foreach ($antrean as [$judul, $ket, $rows])
    <section class="panel flex flex-col">
      <div class="border-b border-kabut-200 px-5 py-4">
        <div class="flex items-center justify-between gap-2"><h2>{{ $judul }}</h2><span class="num rounded-full bg-kabut-100 px-2 py-0.5 text-xs font-bold text-kabut-600">{{ $rows->count() }}</span></div>
        <p class="mt-1 text-xs text-kabut-500">{{ $ket }}</p>
      </div>
      <ul class="flex-1 divide-y divide-kabut-100">
        @forelse ($rows as $r)
          <li>
            <a href="{{ route('permohonan.show', $r) }}" class="block px-5 py-3.5 text-tinta no-underline hover:bg-kabut-50 hover:no-underline">
              <span class="flex items-center justify-between gap-2"><span class="kode text-sm">{{ $r->kode }}</span><span class="text-xs text-kabut-500" title="{{ tanggal($r->updated_at) }}">{{ $r->updated_at->locale('id')->diffForHumans() }}</span></span>
              <span class="mt-0.5 block text-sm font-semibold">{{ $r->nama_sampel }}</span>
              <span class="block text-xs text-kabut-500">{{ $r->pemohon?->instansi ?: $r->pemohon?->nama }}, {{ $r->jumlah_sampel }} sampel</span>
              @if ($r->lhu)<span class="mt-1.5 inline-block {{ $r->lhu->status_izin === \App\Enums\StatusIzin::Lulus ? 'badge-ok' : 'badge-bad' }}">Rekap: {{ $r->lhu->status_izin->label() }}</span>@endif
            </a>
          </li>
        @empty
          <li class="px-5 py-8 text-center text-sm text-kabut-500">Tidak ada yang menunggu.</li>
        @endforelse
      </ul>
    </section>
  @endforeach
</div>
@endsection
