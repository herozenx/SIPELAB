@php
  $urlCek = route('verifikasi', $lhu->kode_verifikasi);
  $S = \App\Models\Setting::class;
  $pemohon = $p->pemohon;
  $tglUji = $p->tahap->min('masuk_at');
  $tglUjiAkhir = $p->tahap->max('selesai_at');
  $rentangUji = $tglUji ? tanggal($tglUji, false).($tglUjiAkhir && tanggal($tglUjiAkhir, false) !== tanggal($tglUji, false) ? ' – '.tanggal($tglUjiAkhir, false) : '') : '–';
  $bulanPanjang = fn ($d) => $d ? $d->copy()->setTimezone(config('app.timezone'))->locale('id')->translatedFormat('j F Y') : '–';
  $belumAkreditasi = $parameter->contains(fn ($x) => ! $x->akreditasi);
  $paraf = $p->persetujuan->where('jenis', \App\Models\Persetujuan::PARAF);
  $periksa = $p->persetujuan->firstWhere('jenis', \App\Models\Persetujuan::PERIKSA);
@endphp
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LHP {{ $lhu->nomor }}</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
  <script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}" defer></script>
</head>
<body class="bg-kabut-200 py-8 print:bg-white print:py-0">
  <div class="no-print mx-auto mb-4 flex max-w-[210mm] flex-wrap items-center justify-between gap-3 px-4">
    <a href="{{ route('permohonan.show', $p) }}" class="inline-flex items-center gap-1 text-sm font-semibold no-underline"><x-ikon n="kembali" class="h-4 w-4" />Kembali ke {{ $p->kodeTampil() }}</a>
    <button type="button" class="btn-primary" data-print><x-ikon n="cetak" class="h-4 w-4" />Cetak atau simpan PDF</button>
  </div>
  <div class="no-print mx-auto mb-4 max-w-[210mm] px-4">
    @unless ($utuh)
      <div class="alert-error"><x-ikon n="silang" class="mt-0.5 h-4 w-4 shrink-0" />Isi LHP tidak cocok dengan sidik saat disahkan. Jangan gunakan dokumen ini dan laporkan ke Administrator.</div>
    @else
      <div class="alert-warning"><x-ikon n="kunci" class="mt-0.5 h-4 w-4 shrink-0" />Dokumen rahasia. Cetak hanya untuk diserahkan kepada pelanggan yang bersangkutan.</div>
    @endunless
  </div>

  @foreach ($halaman as $hal)
  @php $perBidang = $hal['parameter']->groupBy('bidang'); @endphp
  <article class="mx-auto mb-6 min-h-[297mm] max-w-[210mm] bg-white px-[16mm] py-[12mm] text-[12px] leading-snug text-tinta shadow-xl print:mb-0 print:min-h-0 print:shadow-none print:break-after-page">
    <header class="flex items-center gap-4 border-b-2 border-tinta pb-3">
      <img src="{{ asset('img/logo-kkp.png') }}" alt="Logo KKP" class="h-14 w-auto">
      <div class="flex-1 text-center">
        <p class="text-[11px] font-bold uppercase">Kementerian Kelautan dan Perikanan</p>
        <p class="text-[13px] font-extrabold uppercase leading-tight">{{ $S::get('nama_instansi') }}</p>
        <p class="text-[10px]">{{ $S::get('alamat_instansi') }}</p>
      </div>
      <div class="text-center text-[7px] leading-tight"><img src="{{ asset('img/logo-kan.png') }}" alt="Logo KAN" class="h-9 w-auto">Laboratorium Penguji<br>LP-573-IDN</div>
    </header>

    <h1 class="mt-4 text-center text-[15px] font-extrabold uppercase tracking-wide underline underline-offset-4">Hasil Uji</h1>
    <p class="text-center text-[11px] italic">Test Result</p>
    <p class="num mt-1 text-center font-semibold">Nomor/<i>Number</i> : {{ $lhu->nomor }}</p>

    <p class="mt-4">Menyatakan bahwa :<br><i class="text-[11px]">This is certify that</i></p>
    <table class="mt-2 w-full">
      <tbody class="[&_td]:py-0.5 [&_td]:align-top">
        @foreach ([
          ['Pelanggan', 'Customer', $pemohon->instansi ?: $pemohon->nama],
          ['Alamat', 'Address', $p->alamat ?: ($pemohon->alamat ?: '-')],
          ['Tanggal Penerimaan', 'Receipt date', $bulanPanjang($p->diterima_at ?? $p->created_at)],
          ['Jenis sampel', 'Type of sample', $hal['nama']],
          ['Kode sampel', 'Sample code', $hal['kode'] ?: '-'],
          ['Tanggal Pengujian', 'Testing date', $rentangUji],
        ] as $i => [$id, $en, $isi])
          <tr><td class="w-6">{{ $i + 1 }}.</td><td class="w-48">{{ $id }}/ <i>{{ $en }}</i></td><td class="w-3">:</td><td class="font-semibold">{{ $isi }}</td></tr>
        @endforeach
      </tbody>
    </table>

    @if ($hal['parameter']->isNotEmpty())
      <table class="mt-4 w-full border-collapse border border-tinta text-[11px]">
        <thead>
          <tr class="[&_th]:border [&_th]:border-tinta [&_th]:px-1.5 [&_th]:py-1 [&_th]:text-center [&_th]:align-middle [&_th]:font-bold">
            <th class="w-8">No.</th><th>Bidang Pengujian/<br><i class="font-normal">Test Field</i></th><th>Parameter/<br><i class="font-normal">Parameters</i></th>
            <th>Hasil (satuan)/<br><i class="font-normal">Result (unit)</i></th><th>Persyaratan Mutu/<br><i class="font-normal">Quality Requirement</i></th>
            <th>Metode Acuan/<br><i class="font-normal">Reference Method</i></th><th>Keterangan/<br><i class="font-normal">Information</i></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($perBidang as $bidang => $list)
            @foreach ($list as $j => $x)
              <tr class="[&_td]:border [&_td]:border-tinta [&_td]:px-1.5 [&_td]:py-1 [&_td]:align-top">
                @if ($j === 0)
                  <td class="text-center" rowspan="{{ $list->count() }}">{{ $loop->parent->iteration }}.</td>
                  <td rowspan="{{ $list->count() }}">{{ $bidang }}</td>
                @endif
                <td>{{ $x->nama }}@unless($x->akreditasi)*@endunless</td>
                <td class="{{ $x->memenuhi === false ? 'font-bold' : '' }}">{{ $x->hasil }}{{ $x->satuan && $x->hasil && ! str_contains($x->hasil, $x->satuan) ? ' '.$x->satuan : '' }}</td>
                <td>{{ $x->persyaratan ?: '-' }}</td>
                <td>{{ $x->metode }}</td>
                <td>{{ $x->keterangan }}</td>
              </tr>
            @endforeach
          @endforeach
        </tbody>
      </table>
    @else
      {{-- LHU format lama (sebelum parameter uji) --}}
      <table class="mt-4 w-full border-collapse border border-tinta text-[11px]">
        <thead><tr class="[&_th]:border [&_th]:border-tinta [&_th]:px-2 [&_th]:py-1"><th class="w-8">No.</th><th>Pengujian</th><th>Hasil</th><th>Tanggal uji</th></tr></thead>
        <tbody>
          @foreach ($hasilLama as $i => $h)
            <tr class="[&_td]:border [&_td]:border-tinta [&_td]:px-2 [&_td]:py-1"><td>{{ $i + 1 }}</td><td>{{ $h->lab->nama }}</td><td>{{ $h->positif() ? 'Positif (+)' : 'Negatif (−)' }}</td><td>{{ tanggal($h->created_at, false) }}</td></tr>
          @endforeach
        </tbody>
      </table>
    @endif

    <table class="mt-4 w-full text-[10.5px]">
      <tbody class="[&_td]:align-top">
        <tr><td class="w-20">Catatan/<br><i>Note</i></td><td class="w-3">:</td><td>
          <ol class="list-decimal space-y-1 pl-4">
            <li>Hasil uji ini mewakili populasi yang diambil/ Hasil uji ini hanya berlaku untuk contoh uji yang diuji**)<br><i>This result of the test represent the population taken/ This result of the test are only valid for the tested sample**)</i></li>
            <li>Laporan hasil uji ini terdiri dari 1 (satu) lembar asli (Stempel Asli).<br><i>This report of test consists of 1 (one) page original (Original Sign).</i></li>
            <li>Laporan hasil uji ini tidak boleh digandakan, kecuali secara lengkap dan seijin tertulis Kepala Balai PPMHKP Surabaya II (Stempel Copy).<br><i>This report of test shall not be reproduced (copied) except for the completed one and with the written permission of the Head of the Technical Implementation Unit (Copy Sign).</i></li>
          </ol>
        </td></tr>
      </tbody>
    </table>

    <div class="mt-6 flex items-end justify-between gap-6">
      <div class="flex items-end gap-3">
        <div class="h-[28mm] w-[28mm] shrink-0 [&_svg]:h-full [&_svg]:w-full" role="img" aria-label="QR Code cek keaslian LHP">{!! \App\Support\Qr::svg($urlCek) !!}</div>
        <div class="max-w-[62mm] text-[9.5px] leading-tight">
          <p class="font-semibold">Pindai untuk cek keaslian</p>
          <p class="num break-all">{{ $urlCek }}</p>
          <p class="mt-1">Kode verifikasi <span class="num font-bold tracking-wider">{{ $lhu->kode_verifikasi }}</span></p>
          @if ($periksa || $paraf->isNotEmpty())
            <p class="mt-1.5 text-[8.5px] text-kabut-600">Diperiksa: {{ $periksa?->user->initials() ?? '-' }} · Paraf: {{ $paraf->map(fn ($x) => $x->user->initials().' ('.$x->lab?->nama.')')->join(', ') ?: '-' }}</p>
          @endif
        </div>
      </div>
      <div class="min-w-[62mm] text-center">
        <p>{{ $S::get('kota_lhp') }}, {{ $bulanPanjang($lhu->validated_at) }}</p>
        <p>Kepala UPT/Penanggung jawab Laboratorium**)</p>
        <p class="text-[10.5px]"><i>Head of TIU/Laboratory in Charge**)</i></p>
        <p class="my-2 text-[9px] font-semibold uppercase tracking-wide text-lamun-700">Disahkan secara elektronik</p>
        <p class="font-bold underline underline-offset-2">{{ $lhu->validator?->nama }}</p>
        @if ($lhu->validator?->nip)<p>NIP. {{ $lhu->validator->nip }}</p>@endif
      </div>
    </div>

    <footer class="mt-6 border-t border-kabut-300 pt-2 text-[9.5px]">
      @if ($belumAkreditasi)<p>*) parameter belum akreditasi/<i>parameter not yet accredited</i></p>@endif
      <p>**) Coret yang tidak sesuai/<i>Cross out whichever does not apply</i></p>
      <div class="mt-1 flex justify-between gap-3"><span class="num">{{ $S::get('kode_formulir_lhp') }}</span><span class="num break-all text-kabut-500">SHA-256 {{ $lhu->hash }}</span></div>
    </footer>
  </article>
  @endforeach
</body>
</html>
