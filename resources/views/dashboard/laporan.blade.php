@extends('layouts.app')
@section('title', 'Laporan')
@section('content')
@php
  $S = \App\Enums\Status::class;
  $menunggu = (int) (($st[$S::Diajukan->value] ?? 0) + ($st[$S::MenungguKonfirmasiBayar->value] ?? 0) + ($st[$S::MenungguValidasi->value] ?? 0));
  $maxLab = max(1, $perLab->max(fn ($l) => $l['negatif'] + $l['positif']));
  $maxBln = max(1, $bulanan->max('n'));
  $bln = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
@endphp
<h1>Laporan layanan</h1>
<p class="mt-1 text-kabut-600">Ringkasan seluruh permohonan sejak sistem dipakai.</p>

<dl class="panel mt-6 grid grid-cols-2 divide-kabut-200 sm:grid-cols-3 lg:grid-cols-6 lg:divide-x">
  @foreach ([
    ['Total permohonan', number_format($total, 0, ',', '.')],
    ['Sedang diuji', (int) ($st[$S::DiLab->value] ?? 0)],
    ['Menunggu admin', $menunggu],
    ['LHU lulus', (int) ($iz['LULUS'] ?? 0)],
    ['LHU tidak lulus', (int) ($iz['TIDAK_LULUS'] ?? 0)],
    ['Rata-rata sampai LHU', $rata === null ? '–' : str_replace('.', ',', (string) $rata).' hari'],
  ] as [$label, $nilai])
    <div class="px-5 py-4"><dt class="text-xs font-semibold text-kabut-500">{{ $label }}</dt><dd class="num mt-1 text-xl font-extrabold">{{ $nilai }}</dd></div>
  @endforeach
</dl>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
  <section class="panel">
    <div class="panel-head"><h2>Hasil per laboratorium</h2>
      <p class="flex gap-3 text-xs text-kabut-600"><span class="flex items-center gap-1.5"><i class="block h-2.5 w-2.5 rounded-sm bg-lamun-500"></i>Negatif</span><span class="flex items-center gap-1.5"><i class="block h-2.5 w-2.5 rounded-sm bg-karang-500"></i>Positif</span></p>
    </div>
    <div class="panel-body space-y-4">
      @forelse ($perLab as $l)
        @php $neg = $l['negatif'] / $maxLab * 100; $pos = $l['positif'] / $maxLab * 100; @endphp
        <div>
          <div class="flex justify-between text-sm"><span class="font-semibold">{{ $l['nama'] }}</span><span class="num text-kabut-600">{{ $l['negatif'] }} negatif, {{ $l['positif'] }} positif</span></div>
          <svg class="mt-1.5 h-3 w-full" viewBox="0 0 100 3" preserveAspectRatio="none" role="img" aria-label="{{ $l['nama'] }}: {{ $l['negatif'] }} negatif, {{ $l['positif'] }} positif">
            <rect width="100" height="3" rx="1.5" class="fill-kabut-100"/>
            <rect width="{{ round($neg, 2) }}" height="3" class="fill-lamun-500"/>
            <rect x="{{ round($neg, 2) }}" width="{{ round($pos, 2) }}" height="3" class="fill-karang-500"/>
          </svg>
        </div>
      @empty
        <p class="text-sm text-kabut-500">Belum ada hasil uji.</p>
      @endforelse
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Permohonan per bulan</h2><span class="text-xs text-kabut-500">12 bulan terakhir</span></div>
    <div class="panel-body">
      <svg class="h-48 w-full" viewBox="0 0 360 160" role="img" aria-label="Grafik jumlah permohonan per bulan">
        @foreach ($bulanan as $i => $b)
          @php $h = $b['n'] / $maxBln * 120; $x = $i * 30 + 6; @endphp
          <rect x="{{ $x }}" y="{{ 130 - $h }}" width="18" height="{{ max($h, 1) }}" rx="3" class="{{ $b['n'] ? 'fill-laut-500' : 'fill-kabut-200' }}"><title>{{ $bln[$b['label']->month - 1] }} {{ $b['label']->year }}: {{ $b['n'] }} permohonan</title></rect>
          @if ($b['n'])<text x="{{ $x + 9 }}" y="{{ 124 - $h }}" text-anchor="middle" class="fill-tinta text-[9px] font-bold">{{ $b['n'] }}</text>@endif
          <text x="{{ $x + 9 }}" y="148" text-anchor="middle" class="fill-kabut-500 text-[9px]">{{ $bln[$b['label']->month - 1] }}</text>
        @endforeach
      </svg>
    </div>
  </section>
</div>

<section class="panel mt-6">
  <div class="panel-head"><h2>PNBP terkonfirmasi</h2><p class="num text-xl font-extrabold text-laut-600">{{ rupiah($pnbp) }}</p></div>
  <p class="panel-body text-sm text-kabut-600">Jumlah tagihan dari permohonan yang pembayarannya sudah dikonfirmasi masuk di SIMPONI (sedang diuji, menunggu validasi, dan selesai).</p>
</section>
@endsection
