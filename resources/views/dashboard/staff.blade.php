@extends('layouts.app')
@section('title', 'Beranda')
@section('content')
@php
  $S = \App\Enums\Status::class;
  $u = auth()->user();
  $total = $antrean->sum(fn ($a) => $a['rows']->count());
  $urutPapan = [$S::Diajukan, $S::Diterima, $S::MenungguBilling, $S::MenungguPembayaran, $S::MenungguKonfirmasiBayar, $S::SiapUji, $S::DiLab, $S::MenungguPemeriksaan, $S::MenungguParaf, $S::MenungguPengesahan];
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1>Halo, {{ $u->nama }}</h1>
    <p class="mt-1 text-kabut-600">{{ $u->role->label() }} · {{ $semua ? 'Memantau seluruh alur permohonan.' : $total.' permohonan menunggu tindakan Anda.' }}</p>
  </div>
  <a href="{{ route('permohonan.index') }}" class="btn-ghost">Semua permohonan</a>
</div>

@if ($papan)
  {{-- Papan lintasan: berapa permohonan di setiap tahap --}}
  <section class="panel mt-6 overflow-hidden" aria-label="Posisi seluruh permohonan aktif">
    <ol class="grid grid-cols-2 gap-px bg-kabut-200 sm:grid-cols-3 lg:grid-cols-10">
      @foreach ($urutPapan as $st)
        @php $n = (int) ($hitung[$st->value] ?? 0); @endphp
        <li class="bg-white px-4 py-4">
          <a href="{{ route('permohonan.index', ['status' => $st->value]) }}" class="block text-tinta no-underline hover:no-underline">
            <p class="num text-2xl font-extrabold {{ $n ? match ($st->tone()) { 'lab' => 'text-laut-600', 'wait' => 'text-surya-600', default => 'text-tinta' } : 'text-kabut-300' }}">{{ $n }}</p>
            <p class="mt-0.5 text-xs font-semibold text-kabut-500">{{ $st->label() }}</p>
          </a>
        </li>
      @endforeach
    </ol>
  </section>
@endif

@if ($dikembalikan->isNotEmpty())
  <section class="panel mt-6 overflow-hidden border-l-4 border-surya-500">
    <div class="panel-head"><h2>Dikembalikan untuk perbaikan</h2><span class="text-xs text-kabut-500">Menunggu perbaikan</span></div>
    <ul class="divide-y divide-kabut-100">
      @foreach ($dikembalikan as $k)
        <li><a href="{{ route('permohonan.show', $k->permohonan_id) }}" class="block px-5 py-3 text-tinta no-underline hover:bg-kabut-50 hover:no-underline">
          <span class="flex flex-wrap items-center gap-2"><span class="kode text-sm">{{ $k->permohonan->kodeTampil() }}</span><span class="badge-wait">{{ $k->label() }}</span><span class="text-xs text-kabut-500">{{ $k->lab ? 'Lab '.$k->lab->nama : 'Ke validasi hasil' }} · oleh {{ $k->pengirim->nama }}, {{ $k->created_at->locale('id')->diffForHumans() }}</span></span>
          <span class="mt-1 block text-sm text-kabut-600">{{ $k->catatan }}</span>
        </a></li>
      @endforeach
    </ul>
  </section>
@endif

@if ($lewatNorma->isNotEmpty())
  <section class="panel mt-6 overflow-hidden border-l-4 border-karang-600">
    <div class="panel-head"><h2>Sampel melewati norma waktu</h2>@if(in_array($u->role, [\App\Enums\Role::Superadmin, \App\Enums\Role::Administrasi, \App\Enums\Role::AdminLab], true))<a href="{{ route('tim.index') }}" class="text-sm font-semibold">Atur tim lab</a>@endif</div>
    <div class="overflow-x-auto">
    <table class="tabel tabel-hp">
      <thead><tr><th>Kode sampel</th><th>Lab</th><th>Ditangani</th><th>Batas</th><th>Keterlambatan</th></tr></thead>
      <tbody>
        @foreach ($lewatNorma as $t)
          @php [$tTeks] = $t->norma(); @endphp
          <tr data-href="{{ route('permohonan.show', $t->permohonan_id) }}">
            <td><a href="{{ route('permohonan.show', $t->permohonan_id) }}" class="kode no-underline hover:underline">{{ $t->permohonan->kodeTampil() }}</a><span class="block text-xs text-kabut-500">{{ $t->permohonan->nama_sampel }}</span></td>
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

<div class="mt-6 grid grid-cols-1 gap-6 {{ $antrean->count() > 1 ? 'xl:grid-cols-2 2xl:grid-cols-3' : '' }}">
  @foreach ($antrean as $a)
    <section class="panel flex flex-col">
      <div class="border-b border-kabut-200 px-5 py-4">
        <div class="flex items-center justify-between gap-2"><h2>{{ $a['judul'] }}</h2><span class="num rounded-full bg-kabut-100 px-2 py-0.5 text-xs font-bold text-kabut-600">{{ $a['rows']->count() }}</span></div>
        <p class="mt-1 text-xs text-kabut-500">{{ $a['ket'] }}</p>
      </div>
      <ul class="flex-1 divide-y divide-kabut-100">
        @forelse ($a['rows'] as $r)
          <li>
            <a href="{{ route('permohonan.show', $r) }}" class="block px-5 py-3.5 text-tinta no-underline hover:bg-kabut-50 hover:no-underline">
              <span class="flex items-center justify-between gap-2"><span class="kode text-sm">{{ $r->kodeTampil() }}</span><span class="text-xs text-kabut-500" title="{{ tanggal($r->updated_at) }}">{{ $r->updated_at->locale('id')->diffForHumans() }}</span></span>
              <span class="mt-0.5 block text-sm font-semibold">{{ $r->nama_sampel }}</span>
              <span class="block text-xs text-kabut-500">{{ $r->pemohon?->instansi ?: $r->pemohon?->nama }} · {{ $r->jenis_bayar->label() }}</span>
              @if ($a['status'] === $S::MenungguBilling && $r->lhpTerbit())<span class="badge-info mt-1.5">Pelunasan setelah LHP</span>@endif
            </a>
          </li>
        @empty
          <li class="px-5 py-8 text-center text-sm text-kabut-500">Tidak ada yang menunggu.</li>
        @endforelse
      </ul>
    </section>
  @endforeach
</div>

@if ($lhpSelesai->isNotEmpty())
  <section class="panel mt-6 overflow-hidden">
    <div class="panel-head"><h2>LHP selesai terbaru</h2><span class="text-xs text-kabut-500">Cetak dan serahkan di kantor</span></div>
    <ul class="divide-y divide-kabut-100">
      @foreach ($lhpSelesai as $r)
        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
          <span><a href="{{ route('permohonan.show', $r) }}" class="kode text-sm no-underline hover:underline">{{ $r->kodeTampil() }}</a><span class="block text-xs text-kabut-500">{{ $r->pemohon?->instansi ?: $r->pemohon?->nama }} · LHP <span class="num">{{ $r->lhu?->nomor }}</span></span></span>
          <a href="{{ route('permohonan.lhu', $r) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm"><x-ikon n="cetak" class="h-4 w-4" />Cetak LHP</a>
        </li>
      @endforeach
    </ul>
  </section>
@endif
@endsection
