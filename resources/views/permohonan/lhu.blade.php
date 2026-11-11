@php $lulus = $lhu->status_izin === \App\Enums\StatusIzin::Lulus; $urlCek = route('verifikasi', $lhu->kode_verifikasi); @endphp
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LHU {{ $lhu->nomor }}</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
  <script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}" defer></script>
</head>
<body class="bg-kabut-200 py-8 print:py-0">
  <div class="no-print mx-auto mb-4 flex max-w-[210mm] flex-wrap items-center justify-between gap-3 px-4">
    <a href="{{ route('permohonan.show', $p) }}" class="inline-flex items-center gap-1 text-sm font-semibold no-underline"><x-ikon n="kembali" class="h-4 w-4" />Kembali ke {{ $p->kode }}</a>
    <button type="button" class="btn-primary" data-print><x-ikon n="cetak" class="h-4 w-4" />Cetak atau simpan PDF</button>
  </div>
  @unless ($utuh)
    <div class="no-print mx-auto mb-4 max-w-[210mm] px-4"><div class="alert-error"><x-ikon n="silang" class="mt-0.5 h-4 w-4 shrink-0" />Isi LHU tidak cocok dengan sidik saat diterbitkan. Jangan gunakan dokumen ini dan laporkan ke Superadmin.</div></div>
  @endunless

  <article class="mx-auto min-h-[297mm] max-w-[210mm] bg-white px-[16mm] py-[14mm] text-[13px] leading-relaxed shadow-xl print:min-h-0 print:shadow-none">
    <header class="flex items-center gap-4 border-b-2 border-laut-700 pb-4">
      <img src="{{ asset('favicon.svg') }}" alt="" class="h-14 w-14">
      <div>
        <p class="text-[11px] font-semibold text-kabut-600">Kementerian Kelautan dan Perikanan</p>
        <p class="text-base font-extrabold leading-snug text-laut-700">{{ \App\Models\Setting::get('nama_instansi') }}</p>
        <p class="text-[11px] text-kabut-600">{{ \App\Models\Setting::get('alamat_instansi') }}</p>
      </div>
    </header>

    <h1 class="mt-6 text-center text-lg font-extrabold tracking-wide">Laporan Hasil Uji</h1>
    <p class="num text-center font-semibold">Nomor {{ $lhu->nomor }}</p>

    <table class="mt-6 w-full">
      <tbody class="[&_td]:py-1 [&_td]:align-top">
        <tr><td class="w-44 text-kabut-600">Pemohon</td><td class="w-3">:</td><td class="font-semibold">{{ $p->pemohon->instansi ?: $p->pemohon->nama }}</td></tr>
        <tr><td class="text-kabut-600">Kode permohonan</td><td>:</td><td class="num">{{ $p->kode }}</td></tr>
        <tr><td class="text-kabut-600">Nama sampel</td><td>:</td><td>{{ $p->nama_sampel }}</td></tr>
        <tr><td class="text-kabut-600">Jenis komoditas</td><td>:</td><td>{{ $p->jenis_komoditas }}</td></tr>
        <tr><td class="text-kabut-600">Jumlah sampel</td><td>:</td><td class="num">{{ $p->jumlah_sampel }}</td></tr>
        <tr><td class="text-kabut-600">Asal sampel</td><td>:</td><td>{{ $p->asal_sampel }}</td></tr>
        <tr><td class="text-kabut-600">Tujuan distribusi</td><td>:</td><td>{{ $p->tujuan->label() }}</td></tr>
        <tr><td class="text-kabut-600">Tanggal diterima</td><td>:</td><td>{{ tanggal($p->created_at, false) }}</td></tr>
      </tbody>
    </table>

    <table class="mt-6 w-full border border-kabut-300 text-left">
      <thead class="bg-kabut-100"><tr class="[&_th]:border [&_th]:border-kabut-300 [&_th]:px-3 [&_th]:py-2"><th class="w-10">No.</th><th>Pengujian</th><th>Hasil</th><th>Tanggal uji</th></tr></thead>
      <tbody>
        @foreach ($hasil as $i => $h)
          <tr class="[&_td]:border [&_td]:border-kabut-300 [&_td]:px-3 [&_td]:py-2">
            <td class="num">{{ $i + 1 }}</td><td>{{ $h->lab->nama }}</td>
            <td class="font-bold {{ $h->positif() ? 'text-karang-600' : 'text-lamun-700' }}">{{ $h->positif() ? 'Positif (+)' : 'Negatif (−)' }}</td>
            <td>{{ tanggal($h->created_at, false) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div class="mt-6 rounded-lg border-2 {{ $lulus ? 'border-lamun-600' : 'border-karang-600' }} px-4 py-3">
      <p class="text-[11px] font-semibold text-kabut-600">Kesimpulan</p>
      <p class="text-base font-extrabold {{ $lulus ? 'text-lamun-700' : 'text-karang-600' }}">{{ $lhu->status_izin->keputusan($p->tujuan) }}</p>
      <p class="mt-1 text-[12px] text-kabut-600">{{ $lulus ? 'Seluruh pengujian memberikan hasil negatif.' : 'Terdapat hasil positif pada sedikitnya satu pengujian.' }}</p>
    </div>

    <div class="mt-10 flex items-end justify-between gap-6">
      <div class="max-w-[60%] text-[11px] text-kabut-600">
        <p>Keaslian dokumen ini dapat dicek di</p>
        <p class="num break-all font-semibold text-tinta">{{ $urlCek }}</p>
        <p class="mt-1">Kode verifikasi <span class="num font-bold tracking-wider text-tinta">{{ $lhu->kode_verifikasi }}</span></p>
        <p class="num mt-1 break-all text-[9px]">SHA-256 {{ $lhu->hash }}</p>
      </div>
      <div class="text-center">
        <p>Divalidasi secara elektronik</p>
        <p class="text-[12px] text-kabut-600">{{ tanggal($lhu->validated_at) }}</p>
        <p class="mt-10 font-bold underline underline-offset-4">{{ $lhu->validator?->nama }}</p>
        <p class="text-[12px] text-kabut-600">Admin Laboratorium</p>
      </div>
    </div>
  </article>
</body>
</html>
