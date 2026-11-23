@php
  // Mengikuti format "Bukti Tagihan Penerimaan Negara Bukan Pajak (PNBP)" dari SIMPONI.
  $S = \App\Models\Setting::class;
  $pemohon = $p->pemohon;
  $wib = fn ($d) => $d ? $d->copy()->setTimezone(config('app.timezone'))->locale('id')->translatedFormat('d M Y H:i:s').' WIB' : '-';
  $lunas = $p->dibayar_at !== null;
  $rp = fn ($n) => number_format((int) $n, 0, ',', '.');
  $baris = $p->parameter->map(fn ($x) => [$x->nama, $x->tarif, $x->jumlah, 'Per Contoh', $x->subtotal()]);
  $admin = max(0, (int) $p->tagihan - (int) $p->parameter->sum(fn ($x) => $x->subtotal()));
  if ($admin > 0) {
      $baris->push(['Biaya administrasi', $admin, 1, 'Per Permohonan', $admin]);
  }
  $nomorAju = trim(($p->nomor_aju ?: '-').' / '.($p->nomor_pnbp ?: '-'));
@endphp
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Bukti Tagihan {{ $p->kode_billing }}</title>
<style>
  @page { margin: 16mm 14mm; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #111; line-height: 1.4; }
  .judul { text-align: center; font-weight: bold; font-size: 10.5pt; margin: 18px 0 14px; }
  h3 { font-size: 9pt; margin: 12px 0 4px; }
  .data td { padding: 2px 0; }
  .data td.l { width: 62mm; }
  table.detail { width: 100%; border-collapse: collapse; margin-top: 4px; font-size: 8.5pt; }
  table.detail th, table.detail td { border: 0.8px solid #111; padding: 4px 5px; vertical-align: middle; }
  table.detail th { text-align: left; }
  .kanan { text-align: right; }
</style>
</head>
<body>
  <table>
    <tr>
      <td style="width:22mm"><img src="{{ \App\Support\Pdf::logo('kkp') }}" style="width:20mm;height:18.2mm"></td>
      <td style="vertical-align:middle;font-weight:bold;font-size:8.5pt;line-height:1.25">
        KEMENTERIAN KELAUTAN DAN PERIKANAN<br>
        BADAN PENGENDALIAN DAN PENGAWASAN MUTU<br>
        HASIL KELAUTAN DAN PERIKANAN
      </td>
    </tr>
  </table>

  <div class="judul">BUKTI TAGIHAN<br>PENERIMAAN NEGARA BUKAN PAJAK (PNBP)</div>

  <h3>Data Billing Tagihan</h3>
  <table class="data">
    <tr><td class="l">Kode Billing</td><td>: <b>{{ $p->kode_billing }}</b></td></tr>
    <tr><td class="l">Tgl. Billing</td><td>: {{ $wib($p->billing_at) }}</td></tr>
    <tr><td class="l">Tgl. Kadaluarsa</td><td>: {{ $wib($p->billing_kedaluwarsa_at) }}</td></tr>
    <tr><td class="l">Nama Perusahaan</td><td>: {{ mb_strtoupper($pemohon->instansi ?: $pemohon->nama) }}</td></tr>
    <tr><td class="l">Satuan Kerja</td><td>: {{ $S::get('satuan_kerja') }}</td></tr>
    <tr><td class="l">Total Setoran</td><td>: Rp. {{ $rp($p->tagihan) }}</td></tr>
    <tr><td class="l">Terbilang</td><td>: {{ terbilang((int) $p->tagihan) }}</td></tr>
  </table>

  <h3>Data Pembayaran Tagihan</h3>
  <table class="data">
    <tr><td class="l">Tgl Pembayaran</td><td>: {{ $lunas ? $wib($p->dibayar_at) : '-' }}</td></tr>
    <tr><td class="l">Bank / Pos Bayar</td><td>: {{ $p->bank_bayar }}</td></tr>
    <tr><td class="l">Channel Bayar</td><td>: {{ $p->channel_bayar }}</td></tr>
    <tr><td class="l">NTB</td><td>: {{ $p->ntb }}</td></tr>
    <tr><td class="l">NTPN</td><td>: {{ $p->ntpn }}</td></tr>
    <tr><td class="l">Status</td><td>: {{ $lunas ? 'Sudah Dibayar' : 'Belum Dibayar' }}</td></tr>
  </table>

  <h3 style="font-size:10pt;margin-top:14px">Detail Tagihan</h3>
  <table class="detail">
    <thead>
      <tr><th style="width:7mm">No</th><th style="width:42mm">Nomor Aju / Nomor PNBP</th><th>Jenis Tarif</th><th class="kanan" style="width:18mm">Tarif</th><th class="kanan" style="width:13mm">Jumlah</th><th style="width:21mm">Satuan</th><th class="kanan" style="width:22mm">Total</th></tr>
    </thead>
    <tbody>
      @foreach ($baris as $i => [$jenis, $tarif, $jumlah, $satuan, $total])
        <tr>
          <td>{{ $i + 1 }}</td><td>{{ $nomorAju }}</td><td>{{ $jenis }}</td>
          <td class="kanan">{{ $rp($tarif) }}</td><td class="kanan">{{ $jumlah }}</td><td>{{ $satuan }}</td><td class="kanan">{{ $rp($total) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
  <p style="margin-top:10px;font-size:7.5pt;color:#555">Kode sampel: {{ $p->kodeTampil() }}. Bayar melalui teller bank, ATM, internet/mobile banking, atau kantor pos yang melayani SIMPONI sebelum tanggal kedaluwarsa.</p>
</body>
</html>
