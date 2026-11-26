@php
  // Agenda Pemeriksaan mikrobiologi (L/OK/SUB II/8.6c), satu halaman = jumlah baris formulir asli; sisa baris dibiarkan kosong.
  $per = max(1, (int) ($d['per_halaman'] ?? 10));
  $halaman = collect($blok)->chunk($per)->values();
  if ($halaman->isEmpty()) { $halaman = collect([collect()]); }
  $banyakKolom = 5 + count($d['kolom']);
@endphp
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Agenda Pemeriksaan {{ $d['jenis'] }} {{ $bulan->format('m-Y') }}</title>
<style>
  @page { margin: 9mm 8mm 8mm; }
  body { font-family: 'DejaVu Sans', sans-serif; color: #111; font-size: {{ $banyakKolom > 30 ? '5.6pt' : ($banyakKolom > 15 ? '6.4pt' : '7.4pt') }}; }
  .am-judul { text-align: center; font-size: 9pt; line-height: 1.25; margin-bottom: 5px; }
  table.am { width: 100%; border-collapse: collapse; }
  table.am th, table.am td { border: 0.6pt solid #111; padding: 1px 1.5px; vertical-align: middle; }
  table.am th { text-align: center; font-weight: bold; }
  table.am tbody.am-blok { border-top: 1.2pt solid #111; }
  table.am td { height: {{ count($d['baris']) > 1 ? '9pt' : '15pt' }}; }
  .am-c, .am-lbl { text-align: center; }
  .am-lbl { white-space: nowrap; }
  .am-acuan td { text-align: center; font-weight: bold; }
  .am-acuan td:first-child { text-align: left; }
  .am-kaki { margin-top: 2px; font-size: 6.5pt; }
  .am-form { float: right; }
</style>
</head>
<body>
@foreach ($halaman as $isi)
  <div @if (! $loop->last) style="page-break-after:always" @endif>
    @include('partials.agenda-mikro', ['d' => $d, 'blok' => $isi->values()->all(), 'nama' => null, 'kosong' => $per - $isi->count()])
  </div>
@endforeach
</body>
</html>
