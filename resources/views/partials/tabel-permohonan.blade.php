{{-- $rows, $labs, $pemohon (bool). Di HP tiap baris tampil sebagai kartu (kelas tabel-hp). --}}
<div class="overflow-x-auto">
<table class="tabel tabel-hp">
  <thead><tr>
    <th>Kode</th><th>Sampel</th>@if($pemohon)<th>Pemohon</th>@endif<th>Jalur</th><th>Status</th><th class="text-right">Diperbarui</th>
  </tr></thead>
  <tbody>
  @foreach ($rows as $r)
    <tr data-href="{{ route('permohonan.show', $r) }}">
      <td class="whitespace-nowrap"><a href="{{ route('permohonan.show', $r) }}" class="kode no-underline hover:underline">{{ $r->kode }}</a></td>
      <td class="min-w-[180px]"><span class="font-semibold">{{ $r->nama_sampel }}</span><span class="block text-xs text-kabut-500">{{ $r->jenis_komoditas }}, {{ $r->jumlah_sampel }} sampel, {{ $r->tujuan->label() }}</span></td>
      @if($pemohon)<td class="min-w-[150px]" data-label="Pemohon">{{ $r->pemohon?->instansi ?: $r->pemohon?->nama }}</td>@endif
      <td data-label="Jalur"><x-jalur-mini :p="$r" :labs="$labs" /></td>
      <td data-label="Status"><x-status :p="$r" /></td>
      <td class="num whitespace-nowrap text-right text-kabut-500" data-label="Diperbarui">{{ tanggal($r->updated_at) }}</td>
    </tr>
  @endforeach
  </tbody>
</table>
</div>
