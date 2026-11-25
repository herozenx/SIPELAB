@php
  // Mengikuti "Lembar Disposisi" Laboratorium Penguji (FL/OK/SUB II/8.5.2.a): satu baris per produk/kode sampel.
  $baris = collect();
  foreach ($rows as $p) {
      $produk = $p->produk->isNotEmpty() ? $p->produk : collect([(object) ['id' => null, 'kode' => $p->kode_sampel, 'nama' => $p->nama_sampel]]);
      foreach ($produk as $pr) {
          $param = $pr->id ? $p->parameter->where('produk_id', $pr->id) : $p->parameter;
          $analis = [];
          foreach ($labs as $lab) {
              $nama = $param->where('lab_id', $lab->id)->map(fn ($x) => $x->analis?->nama)->filter()->unique();
              if ($nama->isEmpty() && $param->where('lab_id', $lab->id)->isNotEmpty()) {
                  $nama = $p->tahap->firstWhere('lab_id', $lab->id)?->operator->pluck('nama') ?? collect();
              }
              $analis[$lab->id] = $nama->join(', ');
          }
          $jumlah = $param->groupBy(fn ($x) => $x->kode ?: $x->nama)->map(fn ($g) => $g->sum('jumlah'));
          $baris->push(['kode' => $pr->kode ?: $p->kode_sampel, 'nama' => $pr->nama, 'analis' => $analis, 'jumlah' => $jumlah]);
      }
  }
  $labDipakai = $labs->filter(fn ($l) => $baris->contains(fn ($b) => $b['analis'][$l->id] !== '') || $rows->contains(fn ($p) => $p->parameter->contains('lab_id', $l->id)))->values();
  if ($labDipakai->isEmpty()) { $labDipakai = $labs; }
@endphp
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Lembar Disposisi {{ $tanggal->format('d-m-Y') }}</title>
<style>
  @page { margin: 10mm 10mm 10mm; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.5pt; color: #111; }
  table.data { width: 100%; border-collapse: collapse; }
  table.data th, table.data td { border: 0.6pt solid #111; padding: 2px 3px; vertical-align: middle; }
  table.data th { font-size: 7pt; }
  .c { text-align: center; }
  .kecil { font-size: 6.5pt; }
</style>
</head>
<body>
  <table style="width:100%">
    <tr>
      <td style="width:30mm"><img src="{{ \App\Support\Pdf::logo('kkp') }}" style="width:18mm;height:16.4mm"></td>
      <td class="c" style="vertical-align:middle">
        <div style="font-size:9pt;font-weight:bold">LABORATORIUM PENGUJI</div>
        <div style="font-size:8pt">BALAI PENGENDALIAN DAN PENGAWASAN MUTU HASIL<br>KELAUTAN DAN PERIKANAN SURABAYA II</div>
        <div style="font-size:11pt;font-weight:bold;margin-top:8px">LEMBAR DISPOSISI</div>
      </td>
      <td style="width:30mm;text-align:center;font-size:5.5pt;vertical-align:middle"><img src="{{ \App\Support\Pdf::logo('kan') }}" style="width:25mm;height:10.2mm"><br>Laboratorium Penguji<br>LP-573-IDN</td>
    </tr>
  </table>

  <p style="margin:6px 0"><b>Tanggal :</b> &nbsp; {{ $tanggal->locale('id')->translatedFormat('j F Y') }}<br><i class="kecil">Date</i></p>

  <table class="data">
    <thead>
      <tr>
        <th rowspan="2" style="width:24mm">Kode Sampel</th>
        <th rowspan="2" style="width:40mm">Jenis Contoh Uji</th>
        <th colspan="{{ $labDipakai->count() }}">Analis</th>
        <th colspan="{{ max(1, $kodeParam->count()) }}">Parameter (jumlah uji)</th>
      </tr>
      <tr>
        @foreach ($labDipakai as $lab)<th>{{ $lab->nama }}</th>@endforeach
        @forelse ($kodeParam as $k)<th class="c">{{ $k }}</th>@empty<th>–</th>@endforelse
      </tr>
    </thead>
    <tbody>
      @forelse ($baris as $b)
        <tr>
          <td>{{ $b['kode'] }}</td>
          <td>{{ mb_strtoupper($b['nama']) }}</td>
          @foreach ($labDipakai as $lab)<td>{{ $b['analis'][$lab->id] }}</td>@endforeach
          @forelse ($kodeParam as $k)<td class="c">{{ $b['jumlah'][$k] ?? '' }}</td>@empty<td></td>@endforelse
        </tr>
      @empty
        <tr><td colspan="{{ 2 + $labDipakai->count() + max(1, $kodeParam->count()) }}" class="c" style="padding:10px">Belum ada sampel yang masuk lab pada tanggal ini.</td></tr>
      @endforelse
    </tbody>
  </table>

  <table style="width:100%;margin-top:14px;text-align:center">
    <tr>
      <td>Mengetahui,</td>
      <td>Petugas Preparasi,</td>
      @foreach ($labDipakai as $lab)<td>Penyelia {{ $lab->nama }},</td>@endforeach
    </tr>
    <tr><td colspan="{{ 2 + $labDipakai->count() }}" style="height:16mm"></td></tr>
    <tr>
      <td>( ................................ )</td>
      <td>( ................................ )</td>
      @foreach ($labDipakai as $lab)<td>( {{ $lab->penyelia->first()?->nama ?? '................................' }} )</td>@endforeach
    </tr>
  </table>

  <p class="kecil" style="margin-top:10px">Ket : Kode parameter mengikuti daftar parameter uji (mis. A = ALT, E1/E2 = E. coli, S = Salmonella, VP = Vibrio parahaemolyticus, VC = Vibrio cholerae, SA = Staphylococcus aureus, C = Total Coliform, F = Formalin, H = Histamin, CAP = Chloramphenicol, CS = Cesium-137, O = Organoleptik).</p>
  <p class="kecil" style="font-style:italic">FL/OK/SUB II/8.5.2.a</p>
</body>
</html>
