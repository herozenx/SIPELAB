@php
  $F = \App\Support\FormatLhp::class;
  $pratinjau = $pratinjau ?? false;
  $urlCek = $lhu?->kode_verifikasi ? route('verifikasi', $lhu->kode_verifikasi) : null;
  $ttd = $word->penandatangan($lhu);
  $kepala = $ttd['jabatan'] === \App\Models\Lhu::JABATAN_KEPALA;
  $populasi = ($p->jenis_bayar ?? \App\Enums\JenisBayar::Mandiri)->mewakiliPopulasi();
  $nomor = $word->nomor($p);
  $tglUji = $p->masuk_lab_at ?? $p->diterima_at ?? $p->created_at;
  $kotaTanggal = \App\Models\Setting::get('kota_lhp').', '.$F::tanggal($lhu?->validated_at ?? now());
  $adaIsian = $p->sampel->contains(fn ($s) => $s->hasil->isNotEmpty());
  $coret = fn (bool $on) => $on ? 'line-through' : '';
@endphp
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LHP {{ $lhu?->nomor ?: $p->kode_sampel }}</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
  <script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}" defer></script>
  <style>
    .lhp { font-family: Calibri, Carlito, 'Segoe UI', Arial, sans-serif; font-size: 10pt; line-height: 1.25; color: #000; }
    .lhp table.hasil { border-collapse: collapse; width: 100%; font-size: 9pt; }
    .lhp table.hasil th, .lhp table.hasil td { border: 1px solid #000; padding: 2px 4px; vertical-align: top; }
    .lhp table.hasil th { font-weight: 700; text-align: center; vertical-align: middle; }
    .lhp .isian td { padding: 0 0 1px; vertical-align: top; }
    @media print { .lhp-halaman { page-break-after: always; box-shadow: none; margin: 0; } .lhp-halaman:last-child { page-break-after: auto; } }
  </style>
</head>
<body class="bg-kabut-200 py-8 print:bg-white print:py-0">
  <div class="no-print mx-auto mb-4 flex max-w-[210mm] flex-wrap items-center justify-between gap-3 px-4">
    <a href="{{ route('permohonan.show', $p) }}" class="inline-flex items-center gap-1 text-sm font-semibold no-underline"><x-ikon n="kembali" class="h-4 w-4" />Kembali ke {{ $p->kode_sampel ?: $p->kode }}</a>
    <div class="flex flex-wrap gap-2">
      @can('unduhLhp', $p)<a href="{{ route('permohonan.word', $p) }}" class="btn-ghost"><x-ikon n="berkas" class="h-4 w-4" />Unduh Word</a>@endcan
      <button type="button" class="btn-primary" data-print><x-ikon n="cetak" class="h-4 w-4" />Cetak atau simpan PDF</button>
    </div>
  </div>
  @if ($pratinjau && ! $lhu?->nomor)
    <div class="no-print mx-auto mb-4 max-w-[210mm] px-4"><div class="alert-warning"><x-ikon n="info" class="mt-0.5 h-4 w-4 shrink-0" />Pratinjau sebelum terbit. Nomor urut LHP diisi saat Admin Lab menerbitkan.</div></div>
  @endif
  @if ($lhu?->nomor)
    <div class="no-print mx-auto mb-4 max-w-[210mm] px-4">
      @unless ($utuh)
        <div class="alert-error"><x-ikon n="silang" class="mt-0.5 h-4 w-4 shrink-0" />Isi LHP tidak cocok dengan sidik saat diterbitkan. Jangan gunakan dokumen ini dan laporkan ke Superadmin.</div>
      @else
        <p class="rounded-lg bg-white px-4 py-3 text-xs text-kabut-600">Keaslian dapat dicek di <span class="num font-semibold text-tinta">{{ $urlCek }}</span> · kode verifikasi <span class="num font-bold tracking-wider text-tinta">{{ $lhu->kode_verifikasi }}</span></p>
      @endunless
    </div>
  @endif

  @if (! $adaIsian)
    {{-- LHU lama (sebelum isian parameter per sampel) --}}
    <article class="mx-auto max-w-[210mm] bg-white px-[16mm] py-[14mm] text-[13px] shadow-xl print:shadow-none">
      <p class="font-semibold">Nomor {{ $lhu?->nomor }}</p>
      <table class="mt-4 w-full border border-kabut-300 text-left">
        <thead class="bg-kabut-100"><tr class="[&_th]:border [&_th]:px-3 [&_th]:py-2"><th>Pengujian</th><th>Hasil</th><th>Tanggal uji</th></tr></thead>
        <tbody>
          @foreach ($p->hasil->sortBy(fn ($h) => $h->lab->urutan) as $h)
            <tr class="[&_td]:border [&_td]:px-3 [&_td]:py-2"><td>{{ $h->lab->nama }}</td><td>{{ $h->label() }}</td><td>{{ tanggal($h->created_at, false) }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </article>
  @endif

  @foreach ($adaIsian ? $p->sampel : [] as $s)
    <article class="lhp lhp-halaman mx-auto mb-8 min-h-[297mm] max-w-[210mm] bg-white px-[18mm] pb-[12mm] pt-[38mm] shadow-xl print:mb-0 print:min-h-0">
      <p class="text-center">Nomor/<i>Number</i> : {{ $nomor }}</p>
      <p class="mt-4">Menyatakan bahwa :</p>
      <p><i>This is certify that</i></p>
      <table class="isian mt-1 w-full">
        @foreach ([
          ['Pelanggan', 'Customer', $word->pelanggan($p)],
          ['Alamat', 'Address', $p->alamat ?: '-'],
          ['Tanggal Penerimaan', 'Receipt date', $F::tanggal($p->diterima_at ?? $p->created_at)],
          ['Jenis sampel', 'Type of sample', $s->jenis_sampel],
          ['Kode sampel', 'Sample code', $s->kode ?: ($p->kode_sampel ?: '-')],
          ['Tanggal Pengujian', 'Testing date', $F::tanggal($tglUji)],
        ] as $i => [$id, $en, $nilai])
          <tr><td class="w-[5mm] pl-[3mm]">{{ $i + 1 }}.</td><td class="w-[52mm] pl-[3mm]">{{ $id }}/ <i>{{ $en }}</i></td><td class="w-[3mm]">:</td><td>{{ $nilai }}</td></tr>
        @endforeach
      </table>

      <table class="hasil mt-3">
        <thead>
          <tr><th class="w-[9mm]">No.</th><th>Bidang Pengujian/<br><i>Test Field</i></th><th>Parameter/<br><i>Parameters</i></th><th>Hasil (satuan)/<br><i>Result (unit)</i></th><th>Persyaratan Mutu/<br><i>Quality Requirement</i></th><th>Metode Acuan/<br><i>Reference Method</i></th><th>Keterangan/<br><i>Information</i></th></tr>
        </thead>
        <tbody>
          @foreach (\App\Services\LhpWord::tabel($s) as $g)
            @foreach ($g['baris'] as $i => $b)
              <tr>
                @if ($i === 0)
                  <td rowspan="{{ count($g['baris']) }}">{{ $g['no'] }}</td>
                  <td rowspan="{{ count($g['baris']) }}">{{ $g['bidang'] }}<br><i>{{ $g['bidang_en'] }}</i></td>
                @endif
                <td>{!! $F::html($b['param']) !!}</td>
                <td class="text-center">{!! $F::html($b['hasil']) !!}</td>
                <td class="text-center">{!! $F::html($b['syarat']) !!}</td>
                <td>{!! $F::html($b['metode']) !!}</td>
                <td>{!! $F::html($b['ket']) !!}</td>
              </tr>
            @endforeach
          @endforeach
        </tbody>
      </table>

      <table class="isian mt-3 w-full text-[9pt]">
        <tr><td class="w-[22mm]">Catatan/</td><td class="w-[4mm]">:</td><td>1. <span class="{{ $coret(! $populasi) }}">Hasil uji ini mewakili populasi yang diambil</span>/ <span class="{{ $coret($populasi) }}">Hasil Uji ini hanya berlaku untuk contoh uji yang diuji</span>*)</td></tr>
        <tr><td><i>Note</i></td><td></td><td class="pl-3"><i><span class="{{ $coret(! $populasi) }}">This result of the test represent the population taken</span>/<span class="{{ $coret($populasi) }}">This result of the test are only valid for the tested sample</span>*)</i></td></tr>
        <tr><td></td><td></td><td>2. Laporan hasil uji ini terdiri dari 1 (satu) lembar asli (Stempel Asli)</td></tr>
        <tr><td></td><td></td><td class="pl-3"><i>This report of test consists of 1 (one) page original (Original Sign)</i></td></tr>
        <tr><td></td><td></td><td>3. Laporan hasil uji ini tidak boleh digandakan, kecuali secara lengkap dan seijin tertulis Kepala Balai PPMHKP Surabaya II (stempel Copy)</td></tr>
        <tr><td></td><td></td><td class="pl-3"><i>This report of test shall not be reproduced (copied) except for the completed one and with the written permission of the Head of Technical Implementation Unit of Marine and Fisheries Quality Assurance Agency Surabaya II (Copy sign)</i></td></tr>
      </table>

      <div class="ml-auto mt-6 w-[75mm]">
        <p>{{ $kotaTanggal }}</p>
        <p><span class="{{ $coret(! $kepala) }}">Kepala UPT</span>/<span class="{{ $coret($kepala) }}">Penanggung jawab Laboratorium</span>**)</p>
        <p><i><span class="{{ $coret(! $kepala) }}">Head of TIU</span>/<span class="{{ $coret($kepala) }}">Laboratory in Charge</span>**)</i></p>
        <p class="mt-16 font-bold underline">{{ $ttd['nama'] }}</p>
        <p>NIP. {{ $ttd['nip'] }}</p>
      </div>

      <div class="mt-6 text-[8pt]">
        <p>*) parameter belum akreditasi/<i>parameter not yet accredited</i></p>
        <p>**) Coret yang tidak sesuai/<i>Cross out whichever does not apply</i></p>
      </div>
      <p class="mt-4 text-right text-[8pt]">FL/OK/LP/SUB II/8.6.d</p>
    </article>
  @endforeach
</body>
</html>
