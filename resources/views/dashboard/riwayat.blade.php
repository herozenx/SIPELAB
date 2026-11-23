@extends('layouts.app')
@section('title', 'Riwayat uji')
@section('content')
<h1>Riwayat uji lab Anda</h1>
<p class="mt-1 text-kabut-600">Semua pengujian yang sudah diselesaikan di lab ini.</p>
<section class="panel mt-6 overflow-hidden">
  @if ($rows->isEmpty())
    <p class="px-6 py-14 text-center text-sm text-kabut-500">Belum ada pengujian yang selesai.</p>
  @else
  <div class="overflow-x-auto">
  <table class="tabel tabel-hp">
    <thead><tr><th>Kode sampel</th><th>Sampel</th><th>Parameter</th><th>Kesesuaian</th><th class="text-right">Selesai</th></tr></thead>
    <tbody>
    @foreach ($rows as $t)
      @php $j = $jumlahParam->get($t->permohonan_id); $tidak = (int) ($j->tidak ?? 0); @endphp
      <tr data-href="{{ route('permohonan.show', $t->permohonan) }}">
        <td><a href="{{ route('permohonan.show', $t->permohonan) }}" class="kode no-underline hover:underline">{{ $t->permohonan->kodeTampil() }}</a></td>
        <td>{{ $t->permohonan->nama_sampel }}</td>
        <td class="num" data-label="Parameter">{{ (int) ($j->n ?? 0) }}</td>
        <td data-label="Kesesuaian"><span class="{{ $tidak ? 'badge-bad' : 'badge-ok' }}">{{ $tidak ? $tidak.' tidak memenuhi' : 'Semua memenuhi' }}</span></td>
        <td class="num whitespace-nowrap text-right text-kabut-500" data-label="Selesai">{{ tanggal($t->selesai_at) }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
  </div>
  {{ $rows->links() }}
  @endif
</section>
@endsection
