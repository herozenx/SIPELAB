{{-- Isian operator per sampel, ditulis persis seperti di LHP (dipakai Administrasi, Admin, operator, dan pengguna jasa setelah terbit). --}}
@props(['p', 'labId' => null, 'tampilOperator' => true])
@php $F = \App\Support\FormatLhp::class; @endphp
@foreach ($p->sampel as $s)
  @php $rows = $labId ? $s->hasil->where('lab_id', $labId) : $s->hasil; @endphp
  <div class="border-t border-kabut-200 first:border-t-0">
    <p class="flex flex-wrap items-baseline gap-x-3 gap-y-1 bg-kabut-50 px-5 py-2.5 text-sm">
      <span class="kode">{{ $s->kode ?: 'Sampel '.$s->urut }}</span>
      <span class="font-semibold">{{ $s->jenis_sampel }}</span>
      @if ($s->berat)<span class="text-xs text-kabut-500">Berat {{ $s->berat }}</span>@endif
    </p>
    @if ($rows->isEmpty())
      <p class="px-5 py-3 text-sm text-kabut-500">Belum ada parameter yang diisi.</p>
    @else
      <div class="overflow-x-auto">
        <table class="tabel tabel-hp text-sm">
          <thead><tr><th>Bidang</th><th>Parameter</th><th>Hasil (satuan)</th><th>Persyaratan mutu</th><th>Metode acuan</th><th>Keterangan</th>@if($tampilOperator)<th>Operator</th>@endif</tr></thead>
          <tbody>
            @foreach ($rows->sortBy(fn ($h) => [['Organoleptik' => 1, 'Biologi' => 2, 'Kimia' => 3, 'Radionuklida' => 4][$h->bidang] ?? 9, $h->urutan]) as $h)
              <tr class="align-top">
                <td class="whitespace-nowrap" data-label="Bidang">{{ $h->bidang }}/<i class="block text-kabut-500">{{ $h->bidang_en }}</i></td>
                <td class="font-semibold" data-label="Parameter">{!! $F::html($h->nama) !!}@if($h->melebihi)<span class="mt-1 block badge-bad w-fit">Melebihi persyaratan</span>@endif</td>
                <td class="num whitespace-nowrap" data-label="Hasil">{!! $F::html($F::hasil($h->ulangan ?? [], $h->satuan)) !!}</td>
                <td data-label="Persyaratan">{!! $F::html($h->persyaratan) ?: '<span class="text-kabut-400">–</span>' !!}</td>
                <td data-label="Metode">{!! $F::html($h->metode) ?: '<span class="text-kabut-400">–</span>' !!}</td>
                <td data-label="Keterangan">{!! $F::html($h->keterangan) ?: '<span class="text-kabut-400">–</span>' !!}</td>
                @if($tampilOperator)<td class="whitespace-nowrap text-kabut-600" data-label="Operator">{{ $h->petugas?->nama }}</td>@endif
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
@endforeach
