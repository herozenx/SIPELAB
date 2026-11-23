@php
  // Mengikuti template "Surat Tugas Pengujian" Laboratorium Penguji (FL/OK/SUB II/8.5.3.d).
  $S = \App\Models\Setting::class;
  $tgl = $surat->first()->created_at->copy()->setTimezone(config('app.timezone'))->locale('id')->translatedFormat('j F Y');
  $dipilih = $surat->pluck('lab_id');
  $centang = fn (bool $ya) => '<span style="display:inline-block;width:10px;height:10px;border:1px solid #111;text-align:center;line-height:10px;font-size:9px">'.($ya ? '✓' : '&nbsp;').'</span>';
@endphp
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Surat Tugas {{ $p->kode_sampel }}</title>
<style>
  @page { margin: 14mm 20mm 12mm; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #111; line-height: 1.35; }
  .tengah { text-align: center; }
  td { vertical-align: top; }
  .label { width: 50mm; font-weight: bold; }
  .titik { width: 6mm; font-weight: bold; }
  .kecil { font-size: 7.5pt; color: #333; }
</style>
</head>
<body>
  <table style="width:100%">
    <tr>
      <td style="width:26mm"><img src="{{ \App\Support\Pdf::logo('kkp') }}" style="width:21mm;height:19.1mm"></td>
      <td class="tengah" style="vertical-align:middle">
        <div style="font-size:12pt;font-weight:bold">LABORATORIUM PENGUJI</div>
        <div style="font-size:9.5pt">UPT BADAN PENGENDALIAN DAN PENGAWASAN<br>MUTU HASIL KELAUTAN DAN PERIKANAN<br>SURABAYA II</div>
      </td>
      <td style="width:26mm;text-align:center;font-size:5.5pt;line-height:1.1;vertical-align:middle"><img src="{{ \App\Support\Pdf::logo('kan') }}" style="width:25mm;height:10.2mm"><br>Laboratorium Penguji<br>LP-573-IDN</td>
    </tr>
  </table>

  <p class="tengah" style="margin:16px 0 18px;font-weight:bold;font-size:10.5pt">SURAT TUGAS PENGUJIAN</p>

  <table style="width:100%">
    <tr>
      <td class="label">Pengujian ditugaskan kepada</td><td class="titik">:</td>
      <td>
        @foreach ($surat as $i => $s)
          <div style="margin-bottom:5px"><b>{{ $i + 1 }}.</b> {{ $s->penyelia?->nama ?? '……………………' }} <span class="kecil">(Penyelia {{ $s->lab->nama }})</span></div>
        @endforeach
      </td>
    </tr>
    <tr><td colspan="3" style="height:8px"></td></tr>
    <tr><td class="label">Jenis Contoh Uji</td><td class="titik">:</td><td>{{ mb_strtoupper($p->nama_sampel) }}@if($p->jumlah_sampel > 1) ({{ $p->jumlah_sampel }} sampel)@endif</td></tr>
    <tr><td class="label">Kode Contoh Uji</td><td class="titik">:</td><td>{{ $p->kode_sampel }}</td></tr>
    <tr><td class="label">No. FPPC</td><td class="titik">:</td><td><b>{{ $surat->first()->nomor }}</b></td></tr>
    <tr><td colspan="3" style="height:6px"></td></tr>
    <tr>
      <td class="label">Parameter</td><td class="titik">:</td>
      <td>
        @foreach ($labs as $lab)
          @php $daftar = $parameter->where('lab_id', $lab->id); @endphp
          <div style="margin-bottom:6px">
            {!! $centang($dipilih->contains($lab->id)) !!} &nbsp;<b>{{ $lab->nama }}</b>
            @if ($daftar->isNotEmpty())
              <div class="kecil" style="margin:2px 0 0 18px">{{ $daftar->map(fn ($x) => $x->nama.' ('.$x->metode.') ×'.$x->jumlah)->join('; ') }}</div>
            @endif
          </div>
        @endforeach
      </td>
    </tr>
    <tr><td colspan="3" style="height:8px"></td></tr>
    <tr><td class="label">Catatan</td><td class="titik">:</td><td>{{ $surat->first()->catatan }}</td></tr>
  </table>

  <table style="width:100%;margin-top:36px">
    <tr>
      <td style="width:55%"></td>
      <td>
        {{ $S::get('kota_surat_tugas') }}, {{ $tgl }}<br>
        {{ $S::get('jabatan_kepala_lab') }},
        <div style="height:20mm"></div>
        {{ $penandaTangan['nama'] ?: '……………………………' }}<br>
        @if ($penandaTangan['nip'])NIP. {{ $penandaTangan['nip'] }}@endif
      </td>
    </tr>
  </table>

  <div class="kecil" style="position:absolute;bottom:0;left:0;font-style:italic">{{ $S::get('kode_formulir_surat_tugas') }}</div>
</body>
</html>
