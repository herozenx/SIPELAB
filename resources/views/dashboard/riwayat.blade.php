@extends('layouts.app')
@section('title', 'Riwayat uji')
@section('content')
<h1>Riwayat uji lab Anda</h1>
<p class="mt-1 text-kabut-600">Semua hasil yang pernah diinput di lab ini.</p>
<section class="panel mt-6 overflow-hidden">
  @if ($rows->isEmpty())
    <p class="px-6 py-14 text-center text-sm text-kabut-500">Belum ada hasil yang diinput.</p>
  @else
  <div class="overflow-x-auto">
  <table class="tabel tabel-hp">
    <thead><tr><th>Kode</th><th>Sampel</th><th>Hasil</th><th>Catatan</th><th class="text-right">Diinput</th></tr></thead>
    <tbody>
    @foreach ($rows as $h)
      <tr data-href="{{ route('permohonan.show', $h->permohonan) }}">
        <td><a href="{{ route('permohonan.show', $h->permohonan) }}" class="kode no-underline hover:underline">{{ $h->permohonan->kode }}</a></td>
        <td>{{ $h->permohonan->nama_sampel }}</td>
        <td data-label="Hasil"><span class="{{ $h->positif() ? 'badge-bad' : 'badge-ok' }}">{{ $h->positif() ? 'Positif' : 'Negatif' }}</span></td>
        <td class="max-w-[280px] truncate text-kabut-600" data-label="Catatan">{{ $h->catatan ?: '–' }}</td>
        <td class="num whitespace-nowrap text-right text-kabut-500" data-label="Diinput">{{ tanggal($h->created_at) }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
  </div>
  {{ $rows->links() }}
  @endif
</section>
@endsection
