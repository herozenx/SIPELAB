@php
  $S = \App\Models\Setting::class;
  $pemohon = $p->pemohon;
  $urlCek = route('verifikasi', $lhu->kode_verifikasi);
  $tgl = fn ($d) => $d ? $d->copy()->setTimezone(config('app.timezone'))->locale('id')->translatedFormat('j F Y') : '-';
  $mulai = $p->tahap->min('masuk_at');
  $akhir = $p->tahap->max('selesai_at');
  $rentang = $mulai ? $tgl($mulai).($akhir && $tgl($akhir) !== $tgl($mulai) ? ' – '.$tgl($akhir) : '') : '-';
  $perBidang = $parameter->groupBy('bidang');
  $belumAkreditasi = $parameter->contains(fn ($x) => ! $x->akreditasi);
  $logo = 'data:image/svg+xml;base64,'.base64_encode((string) @file_get_contents(public_path('favicon.svg')));
@endphp
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>LHP {{ $lhu->nomor }}</title>
<style>
  @page { margin: 9mm 14mm 8mm; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.4pt; color: #111; line-height: 1.22; }
  i { color: #333; }
  .kop { border-bottom: 2px solid #111; padding-bottom: 6px; width: 100%; }
  .kop td { vertical-align: middle; }
  .tengah { text-align: center; }
  h1 { font-size: 12pt; margin: 6px 0 0; text-align: center; text-decoration: underline; text-transform: uppercase; }
  .data td { padding: 0.5px 0; vertical-align: top; }
  table.hasil { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 7.6pt; }
  table.hasil th, table.hasil td { border: 0.8px solid #111; padding: 2px 3px; vertical-align: top; }
  table.hasil th { text-align: center; vertical-align: middle; }
  .catatan td { vertical-align: top; font-size: 7.1pt; }
  .kecil { font-size: 6.8pt; }
  .tebal { font-weight: bold; }
</style>
</head>
<body>
  <table class="kop">
    <tr>
      <td style="width:18mm"><img src="{{ $logo }}" style="width:16mm;height:16mm"></td>
      <td class="tengah">
        <div style="font-size:9pt;font-weight:bold">KEMENTERIAN KELAUTAN DAN PERIKANAN</div>
        <div style="font-size:10.5pt;font-weight:bold;text-transform:uppercase">{{ $S::get('nama_instansi') }}</div>
        <div style="font-size:7.5pt">{{ $S::get('alamat_instansi') }}</div>
      </td>
      <td style="width:18mm"></td>
    </tr>
  </table>

  <h1>Laporan Hasil Pengujian</h1>
  <div class="tengah"><i>Report of Analysis</i></div>
  <div class="tengah tebal" style="margin-top:3px">Nomor/<i>Number</i> : {{ $lhu->nomor }}</div>

  <p style="margin:6px 0 2px">Menyatakan bahwa :<br><i>This is certify that</i></p>
  <table class="data">
    @foreach ([
      ['Pelanggan', 'Customer', $pemohon->instansi ?: $pemohon->nama],
      ['Alamat', 'Address', $p->alamat ?: ($pemohon->alamat ?: '-')],
      ['Tanggal Penerimaan', 'Receipt date', $tgl($p->diterima_at ?? $p->created_at)],
      ['Jenis sampel', 'Type of sample', $p->nama_sampel],
      ['Kode sampel', 'Sample code', $p->kode_sampel ?: '-'],
      ['Tanggal Pengujian', 'Testing date', $rentang],
    ] as $i => [$id, $en, $isi])
      <tr><td style="width:5mm">{{ $i + 1 }}.</td><td style="width:60mm">{{ $id }}/ <i>{{ $en }}</i></td><td style="width:4mm">:</td><td class="tebal">{{ $isi }}</td></tr>
    @endforeach
  </table>

  @if ($parameter->isNotEmpty())
    <table class="hasil">
      <thead>
        <tr>
          <th style="width:6mm">No.</th><th style="width:20mm">Bidang Pengujian/<br><i>Test Field</i></th><th style="width:28mm">Parameter/<br><i>Parameters</i></th>
          <th style="width:22mm">Hasil (satuan)/<br><i>Result (unit)</i></th><th style="width:25mm">Persyaratan Mutu/<br><i>Quality Requirement</i></th>
          <th style="width:30mm">Metode Acuan/<br><i>Reference Method</i></th><th>Keterangan/<br><i>Information</i></th>
        </tr>
      </thead>
      <tbody>
        @foreach ($perBidang as $bidang => $list)
          @foreach ($list->values() as $j => $x)
            <tr>
              @if ($j === 0)
                <td class="tengah" rowspan="{{ $list->count() }}">{{ $loop->parent->iteration }}.</td>
                <td rowspan="{{ $list->count() }}">{{ $bidang }}</td>
              @endif
              <td>{{ $x->nama }}@unless($x->akreditasi)*@endunless</td>
              <td @if($x->memenuhi === false) class="tebal" @endif>{{ $x->hasil }}</td>
              <td>{{ $x->persyaratan ?: '-' }}</td>
              <td>{{ $x->metode }}</td>
              <td>{{ $x->keterangan }}</td>
            </tr>
          @endforeach
        @endforeach
      </tbody>
    </table>
  @else
    <table class="hasil">
      <thead><tr><th style="width:7mm">No.</th><th>Pengujian</th><th>Hasil</th></tr></thead>
      <tbody>@foreach ($hasilLama as $i => $h)<tr><td class="tengah">{{ $i + 1 }}.</td><td>{{ $h->lab->nama }}</td><td>{{ $h->positif() ? 'Positif' : 'Negatif' }}</td></tr>@endforeach</tbody>
    </table>
  @endif

  <table class="catatan" style="margin-top:6px;width:100%">
    <tr>
      <td style="width:16mm">Catatan/<br><i>Note</i></td><td style="width:3mm">:</td>
      <td>
        1. Hasil uji ini hanya berlaku untuk contoh uji yang diuji/ <i>This result of the test are only valid for the tested sample.</i><br>
        2. Laporan hasil uji ini terdiri dari 1 (satu) lembar asli/ <i>This report of test consists of 1 (one) page original.</i><br>
        3. Laporan hasil uji ini tidak boleh digandakan, kecuali secara lengkap dan seijin tertulis Kepala Balai PPMHKP Surabaya II/
        <i>This report of test shall not be reproduced except for the completed one and with the written permission of the Head of the Technical Implementation Unit.</i>
      </td>
    </tr>
  </table>

  <table style="width:100%;margin-top:6px;page-break-inside:avoid">
    <tr>
      <td style="width:55%;vertical-align:bottom">
        <table><tr>
          <td style="width:24mm"><img src="{{ \App\Support\Pdf::svg(\App\Support\Qr::svg($urlCek, 160)) }}" style="width:22mm;height:22mm"></td>
          <td class="kecil" style="vertical-align:middle">
            <b>Pindai untuk cek keaslian</b><br>{{ $urlCek }}<br>
            Kode verifikasi <b>{{ $lhu->kode_verifikasi }}</b>
          </td>
        </tr></table>
      </td>
      <td class="tengah" style="vertical-align:bottom">
        {{ $S::get('kota_lhp') }}, {{ $tgl($lhu->validated_at) }}<br>
        Kepala UPT/Penanggung jawab Laboratorium**)<br>
        <i>Head of TIU/Laboratory in Charge**)</i><br>
        <div style="height:10mm;padding-top:3mm;font-size:6.5pt;color:#2f6b3a">DISAHKAN SECARA ELEKTRONIK</div>
        <b style="text-decoration:underline">{{ $lhu->validator?->nama }}</b><br>
        @if ($lhu->validator?->nip)NIP. {{ $lhu->validator->nip }}@endif
      </td>
    </tr>
  </table>

  <div class="kecil" style="margin-top:6px;border-top:0.6px solid #999;padding-top:3px">
    @if ($belumAkreditasi)*) parameter belum akreditasi/<i>parameter not yet accredited</i><br>@endif
    **) Coret yang tidak sesuai/<i>Cross out whichever does not apply</i><br>
    {{ $S::get('kode_formulir_lhp') }}
  </div>
</body>
</html>
