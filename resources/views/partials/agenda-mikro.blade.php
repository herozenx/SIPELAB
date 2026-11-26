{{--
  Tabel "Agenda Pemeriksaan" mikrobiologi (L/OK/SUB II/8.6c), mengikuti formulir kiriman Balai.
  $d      : definisi dari App\Support\AgendaMikro
  $blok   : daftar sampel (AgendaMikro::blok), boleh lebih dari satu parameter
  $nama   : awalan nama input (mis. "lk[12]") saat diisi; null = hanya tampil (cetak)
  $hasilLhp : saat diisi & satu sampel, ['id' => ..., 'name' => ...] input Hasil LHP yang ditaruh di kolom Hasil
  $kosong : jumlah blok kosong tambahan (cetak formulir)
--}}
@php
  $A = \App\Support\AgendaMikro::class;
  [$atas, $bawah] = $A::judulKolom($d);
  $nb = count($d['baris']);
  $nama = $nama ?? null;
  $hasilLhp = $hasilLhp ?? null;
  $tgl = fn ($t) => $t ? $t->copy()->setTimezone(config('app.timezone'))->format('d/m/y') : '';
  $daftar = collect($blok)->concat(array_fill(0, $kosong ?? 0, null));
  $sel = function ($b, string $kunci, bool $lebar = false) use ($nama, $hasilLhp) {
      if ($b === null) {
          return '';
      }
      $nilai = (string) ($b['isi'][$kunci] ?? '');
      if ($nama === null) {
          return $kunci === 'hasil' ? \App\Support\Pangkat::html($nilai) : e($nilai);
      }
      if ($kunci === 'hasil' && $hasilLhp) {
          return '<input id="'.e($hasilLhp['id']).'" name="'.e($hasilLhp['name']).'" value="'.e(old(str_replace(['[', ']'], ['.', ''], $hasilLhp['name']), $nilai)).'" maxlength="150" class="am-in am-hasil" aria-label="Hasil">'
            .'<button type="button" class="am-x2 num" data-pangkat="'.e($hasilLhp['id']).'" aria-pressed="false" title="Ketik pangkat: tekan x², ketik angka pangkatnya, lalu tekan x² lagi">x²</button>';
      }
      $n = $nama.'['.$b['no'].']['.$kunci.']';

      return '<input name="'.e($n).'" value="'.e($nilai).'" maxlength="'.($kunci === 'hasil' ? 60 : 30).'" class="am-in'.($lebar || $kunci === 'hasil' ? ' am-lebar' : '').'" aria-label="'.e($kunci).'">';
  };
  $lebar = $nb === 1;
@endphp
<div class="am-judul"><b>{!! $d['judul'] !!}</b><br><b>{!! $d['metode'] !!}</b></div>
<table class="am">
  <thead>
    <tr>
      <th rowspan="2">{{ $d['awal'][0] }}</th>
      <th rowspan="2" class="am-kode">{{ $d['awal'][1] }}</th>
      @foreach ($atas as [$teks, $cs, $rs])<th colspan="{{ $cs }}" rowspan="{{ $rs }}">{!! $teks !!}</th>@endforeach
      <th rowspan="2">{!! $d['akhir'] !!}</th>
      <th rowspan="2">Analis</th>
      <th rowspan="2">Penyelia</th>
    </tr>
    <tr>@foreach ($bawah as [$teks])<th>{!! $teks !!}</th>@endforeach</tr>
    @if (! empty($d['acuan']))
      <tr class="am-acuan">
        <td colspan="2">{!! $d['acuan']['label'] !!}</td>
        @foreach ($d['kolom'] as $c)<td>{{ isset($c['k']) ? ($d['acuan'][$c['k']] ?? '') : '' }}</td>@endforeach
        <td></td><td></td><td></td>
      </tr>
    @endif
  </thead>
  @foreach ($daftar as $b)
    <tbody class="am-blok">
      @for ($i = 0; $i < $nb; $i++)
        <tr>
          @if ($i === 0)
            <td rowspan="{{ $nb }}" class="am-c">{{ $b ? $tgl($b['tgl']) : '' }}</td>
            <td rowspan="{{ $nb }}" class="am-kode">{{ $b['kode'] ?? '' }}</td>
          @endif
          @foreach ($d['kolom'] as $c)
            @if (isset($c['lbl']))
              @php $r = ! empty($c['gabung']) ? $A::rentangLabel($d, $c['lbl'], $i) : 1; @endphp
              @if ($r > 0)<td rowspan="{{ $r }}" class="am-lbl">{{ $d['baris'][$i][$c['lbl']] ?? '' }}</td>@endif
            @elseif (($c['per'] ?? 'baris') === 'sampel')
              @if ($i === 0)<td rowspan="{{ $nb }}" class="am-c">{!! $sel($b, $c['k'], true) !!}</td>@endif
            @elseif (($c['per'] ?? 'baris') === 'lbl0')
              @php $r = $A::rentangLabel($d, 0, $i); @endphp
              @if ($r > 0)<td rowspan="{{ $r }}" class="am-c">{!! $sel($b, "g{$i}.{$c['k']}") !!}</td>@endif
            @else
              <td class="am-c">{!! $sel($b, "r{$i}.{$c['k']}", $lebar) !!}</td>
            @endif
          @endforeach
          @if ($i === 0)
            <td rowspan="{{ $nb }}" class="am-c">{{ $b ? $tgl($b['keluar']) : '' }}</td>
            <td rowspan="{{ $nb }}" class="am-c">{{ $b['analis'] ?? '' }}</td>
            <td rowspan="{{ $nb }}" class="am-c">{{ $b['penyelia'] ?? '' }}</td>
          @endif
        </tr>
      @endfor
    </tbody>
  @endforeach
</table>
<div class="am-kaki">
  <span>{!! $d['ket'] ?? '' !!}</span>
  <span class="am-form">{{ $A::KODE_FORMULIR }}</span>
</div>
