@extends('layouts.app')
@section('title', 'Antrean lab')
@section('content')
@php $penyelia = $u->role === \App\Enums\Role::Penyelia; @endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1>{{ $penyelia ? 'Penyelia' : 'Antrean' }} Lab {{ $u->lab?->nama ?? '—' }}</h1>
    <p class="mt-1 text-kabut-600">{{ $penyelia ? 'Surat tugas bidang Anda dan pekerjaan analis. ' : '' }}Batas norma terdekat di urutan teratas. Norma Lab {{ $u->lab?->nama }}: {{ $u->lab?->norma_hari }} hari kerja. Anda menginput {{ $hariIni }} hasil parameter hari ini.</p>
  </div>
  <a href="{{ route('riwayat') }}" class="btn-ghost"><x-ikon n="riwayat" class="h-4 w-4" />Riwayat uji</a>
</div>

@unless ($u->lab)
  <div class="alert-warning mt-6"><x-ikon n="info" class="mt-0.5 h-4 w-4 shrink-0" />Akun Anda belum ditempatkan di laboratorium mana pun. Hubungi Admin Laboratorium.</div>
@endunless

@if ($penyelia)
  <section class="panel mt-6 overflow-hidden {{ $paraf->isNotEmpty() ? 'ring-2 ring-laut-500' : '' }}">
    <div class="panel-head"><h2>Menunggu paraf Anda</h2><span class="num rounded-full bg-kabut-100 px-2 py-0.5 text-xs font-bold text-kabut-600">{{ $paraf->count() }}</span></div>
    @if ($paraf->isEmpty())
      <p class="px-5 py-6 text-sm text-kabut-500">Tidak ada LHP yang menunggu paraf bidang Anda.</p>
    @else
      <ul class="divide-y divide-kabut-100">
        @foreach ($paraf as $r)
          <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
            <span><span class="kode text-sm">{{ $r->kodeTampil() }}</span><span class="block text-sm font-semibold">{{ $r->nama_sampel }}</span></span>
            <a href="{{ route('permohonan.show', $r) }}" class="btn-primary btn-sm"><x-ikon n="pena" class="h-4 w-4" />Periksa &amp; paraf</a>
          </li>
        @endforeach
      </ul>
    @endif
  </section>
@endif

<section class="panel mt-6 overflow-hidden">
  <div class="panel-head"><h2>Sampel di lab {{ $penyelia ? 'bidang Anda' : 'Anda' }}</h2><span class="num rounded-full bg-kabut-100 px-2 py-0.5 text-xs font-bold text-kabut-600">{{ $antrean->count() }}</span></div>
  @if ($antrean->isEmpty())
    <div class="px-6 py-14 text-center">
      <p class="font-semibold">Antrean kosong</p>
      <p class="mt-1 text-sm text-kabut-500">Sampel masuk otomatis setelah Operator menerbitkan surat tugas. Halaman ini diperbarui sendiri.</p>
    </div>
  @else
    <div class="overflow-x-auto">
    <table class="tabel tabel-hp">
      <thead><tr><th>No.</th><th>Kode sampel</th><th>Sampel</th><th>Parameter</th><th>Norma waktu</th><th>Ditangani</th><th></th></tr></thead>
      <tbody>
        @foreach ($antrean as $i => $r)
          @php $t = $r->tahap->first(); $isi = $r->parameter->filter->sudahDiisi()->count(); $balik = $r->pengembalian->last(); @endphp
          <tr data-href="{{ route('permohonan.show', $r) }}">
            <td class="num text-kabut-500 hp-sembunyi">{{ $i + 1 }}</td>
            <td class="kode whitespace-nowrap"><span class="sm:hidden text-kabut-500">{{ $i + 1 }}. </span>{{ $r->kodeTampil() }}
              @if ($balik)<span class="badge-wait mt-1 block w-fit">{{ $balik->label() }}</span>@endif
            </td>
            <td><span class="font-semibold">{{ $r->nama_sampel }}</span><span class="block text-xs text-kabut-500">{{ $r->jenis_komoditas }} · {{ $r->jumlah_sampel }} sampel</span></td>
            <td class="whitespace-nowrap" data-label="Parameter"><span class="num font-semibold">{{ $isi }}/{{ $r->parameter->count() }}</span> <span class="text-xs text-kabut-500">terisi</span><span class="block max-w-[200px] truncate text-xs text-kabut-500">{{ $r->parameter->pluck('kode')->filter()->join(', ') }}</span></td>
            <td class="whitespace-nowrap" data-label="Norma waktu">
              <span>
                @if ($t)
                  @php [$nTeks, $nNada] = $t->norma(); @endphp
                  <span class="badge-{{ $nNada }}">{{ $nTeks }}</span>
                  <span class="mt-1 block text-xs text-kabut-500">Batas {{ tanggal($t->batas_at, false) }}</span>
                @endif
              </span>
            </td>
            <td class="min-w-[120px] text-xs" data-label="Ditangani">
              @if ($t && $t->operator->isNotEmpty())<span>{{ $t->operator->pluck('nama')->join(', ') }}</span>@else<span class="text-kabut-500">Belum ada</span>@endif
            </td>
            <td class="text-right max-sm:!pt-2"><span class="inline-flex flex-wrap justify-end gap-2 max-sm:w-full"><a href="{{ route('permohonan.surat-tugas', $r) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm max-sm:flex-1"><x-ikon n="berkas" class="h-4 w-4" />Surat tugas</a><a href="{{ route('permohonan.show', $r) }}" class="btn-primary btn-sm max-sm:flex-1">Input hasil</a></span></td>
          </tr>
        @endforeach
      </tbody>
    </table>
    </div>
  @endif
</section>
<p class="mt-3 text-xs text-kabut-500">Uji dilakukan independen: identitas pemohon, tagihan, dan hasil lab lain tidak ditampilkan kepada petugas lab.</p>
@endsection
