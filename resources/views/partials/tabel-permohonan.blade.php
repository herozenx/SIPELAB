{{-- $rows, $labs, $pemohon (bool). Di HP tiap baris tampil sebagai kartu (kelas tabel-hp).
     Kolom "Jalur" (tahapan lab) hanya untuk petugas Balai, tidak untuk pengguna jasa. --}}
@php $internal = auth()->user()?->isPetugasBalai(); $lab = auth()->user()?->isLab(); @endphp
<div class="overflow-x-auto">
<table class="tabel tabel-hp">
  <thead><tr>
    <th>Kode</th><th>Sampel</th>@if($pemohon)<th>Pemohon</th>@endif @if($internal)<th>Jalur</th>@endif<th>Status</th><th class="text-right">Diperbarui</th>
  </tr></thead>
  <tbody>
  @foreach ($rows as $r)
    <tr data-href="{{ route('permohonan.show', $r) }}">
      <td class="whitespace-nowrap"><a href="{{ route('permohonan.show', $r) }}" class="kode no-underline hover:underline">{{ $r->kodeTampil() }}</a>@if($r->kode_sampel)<span class="block text-xs text-kabut-500">{{ $r->kode }}</span>@endif</td>
      <td class="min-w-[180px]"><span class="font-semibold">{{ $r->nama_sampel }}</span><span class="block text-xs text-kabut-500">{{ $r->jenis_komoditas }}, {{ $r->jumlah_sampel }} sampel{{ $lab ? '' : ', '.$r->jenis_bayar->label() }}</span></td>
      @if($pemohon)<td class="min-w-[150px]" data-label="Pemohon">{{ $r->pemohon?->instansi ?: $r->pemohon?->nama }}</td>@endif
      @if($internal)<td data-label="Jalur"><x-jalur-mini :p="$r" :labs="$labs" /></td>@endif
      <td data-label="Status"><x-status :p="$r" /></td>
      <td class="num whitespace-nowrap text-right text-kabut-500" data-label="Diperbarui">{{ tanggal($r->updated_at) }}</td>
    </tr>
  @endforeach
  </tbody>
</table>
</div>
