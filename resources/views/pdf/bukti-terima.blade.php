@php
  $S = \App\Models\Setting::class;
  $pemohon = $p->pemohon;
  $tgl = fn ($d) => $d ? $d->copy()->setTimezone(config('app.timezone'))->locale('id')->translatedFormat('j F Y, H.i') : '-';
  $logo = 'data:image/svg+xml;base64,'.base64_encode((string) @file_get_contents(public_path('favicon.svg')));
@endphp
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Bukti Terima Sampel {{ $p->kode_sampel }}</title>
<style>
  @page { margin: 16mm 18mm; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #111; line-height: 1.35; }
  .kop { border-bottom: 2px solid #111; padding-bottom: 6px; width: 100%; }
  .tengah { text-align: center; }
  h1 { font-size: 13pt; margin: 12px 0 2px; text-align: center; text-decoration: underline; text-transform: uppercase; }
  .data td { padding: 2px 0; vertical-align: top; }
  table.tabel { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 8.8pt; }
  table.tabel th, table.tabel td { border: 0.8px solid #111; padding: 3px 5px; }
  .kecil { font-size: 7.8pt; color: #444; }
</style>
</head>
<body>
  <table class="kop">
    <tr>
      <td style="width:18mm"><img src="{{ $logo }}" style="width:16mm;height:16mm"></td>
      <td class="tengah">
        <div style="font-weight:bold">KEMENTERIAN KELAUTAN DAN PERIKANAN</div>
        <div style="font-size:10.5pt;font-weight:bold;text-transform:uppercase">{{ $S::get('nama_instansi') }}</div>
        <div style="font-size:7.5pt">{{ $S::get('alamat_instansi') }}</div>
      </td>
      <td style="width:18mm"></td>
    </tr>
  </table>

  <h1>Bukti Terima Sampel</h1>
  <div class="tengah"><b>Kode sampel: {{ $p->kode_sampel }}</b></div>

  <table class="data" style="margin-top:12px">
    @foreach ([
      ['Pelanggan', $pemohon->instansi ?: $pemohon->nama],
      ['Alamat', $p->alamat ?: ($pemohon->alamat ?: '-')],
      ['Penanggung jawab', $pemohon->nama.($pemohon->telepon ? ' ('.$pemohon->telepon.')' : '')],
      ['Jenis sampel', $p->nama_sampel.' ('.$p->jenis_komoditas.')'],
      ['Jumlah sampel', $p->jumlah_sampel],
      ['Asal sampel', $p->asal_sampel],
      ['Kondisi saat diterima', collect([$p->bentuk, $p->kemasan, $p->suhu !== null ? $p->suhu.' °C' : null, $p->berat])->filter()->join(', ') ?: '-'],
      ['Jenis layanan', $p->jenis_bayar->label().' ('.$p->jenis_bayar->keterangan().')'],
      ['Tanggal diterima', $tgl($p->diterima_at)],
    ] as [$label, $isi])
      <tr><td style="width:48mm">{{ $label }}</td><td style="width:4mm">:</td><td><b>{{ $isi }}</b></td></tr>
    @endforeach
  </table>

  <p style="margin:12px 0 2px">Parameter uji yang diminta:</p>
  <table class="tabel">
    <thead><tr><th style="width:8mm">No.</th><th>Bidang</th><th>Parameter</th><th>Metode acuan</th><th style="width:18mm">Jumlah uji</th></tr></thead>
    <tbody>
      @foreach ($p->parameter as $i => $x)
        <tr><td class="tengah">{{ $i + 1 }}</td><td>{{ $x->bidang }}</td><td>{{ $x->nama }}</td><td>{{ $x->metode }}</td><td class="tengah">{{ $x->jumlah }}</td></tr>
      @endforeach
    </tbody>
  </table>

  <table style="width:100%;margin-top:22px">
    <tr>
      <td class="tengah" style="width:50%">Pemohon,<div style="height:18mm"></div>( {{ $pemohon->nama }} )</td>
      <td class="tengah">Petugas Penerima Contoh Uji,<div style="height:18mm"></div>( {{ $p->penerima?->nama ?? '……………………' }} )</td>
    </tr>
  </table>

  <p class="kecil" style="margin-top:18px">Simpan bukti ini. Sebutkan kode sampel saat menanyakan perkembangan pengujian. Status permohonan dapat dipantau di SILANTRO.<br>
  Dicetak {{ $tgl(now()) }}.</p>
</body>
</html>
