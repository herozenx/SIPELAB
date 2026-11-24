@extends('layouts.app')
@section('title', 'Beranda')
@section('content')

{{-- Header halaman --}}
<div class="flex flex-wrap items-start justify-between gap-3">
  <div>
    <h1>Halo, {{ $u->nama }}</h1>
    @if($u->instansi)
      <p class="mt-1 text-sm text-kabut-600">{{ $u->instansi }}</p>
    @endif
  </div>
  <a href="{{ route('permohonan.create') }}" class="btn-primary shrink-0 transition-transform duration-150 active:scale-[0.98]">
    <x-ikon n="tambah" class="h-4 w-4" />Ajukan uji baru
  </a>
</div>

{{-- Aksi mendesak: tagihan menunggu pembayaran --}}
@foreach ($perluTindakan as $p)
  <a href="{{ route('permohonan.show', $p) }}"
     class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-laut-600 px-5 py-4 text-white no-underline shadow-sm transition-all duration-200 hover:bg-laut-700 hover:shadow-md hover:no-underline">
    <span class="flex min-w-0 items-start gap-3">
      <span class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/10 text-white">
        <x-ikon n="peringatan" class="h-5 w-5" />
      </span>
      <span class="min-w-0">
        <span class="block font-bold leading-snug">
          {{ $p->lhpTerbit() ? 'LHP '.$p->kodeTampil().' selesai, bayar tagihan untuk mengunduh' : 'Bayar tagihan '.$p->kodeTampil() }}
        </span>
        <span class="mt-1 block text-sm leading-relaxed text-laut-100">
          {{ rupiah($p->tagihan) }} lewat SIMPONI &middot;
          kode billing <span class="num font-semibold text-white">{{ $p->kode_billing }}</span>,
          lalu unggah bukti bayar.
        </span>
      </span>
    </span>
    <span class="btn shrink-0 bg-white text-laut-700 transition-colors hover:bg-laut-50">Lihat billing</span>
  </a>
@endforeach

{{-- Stat Cards: 2 kolom di mobile agar hemat ruang dan tidak menumpuk 4 kartu tinggi, 4 kolom di desktop --}}
<div class="mt-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
  {{-- Card 1: Total Pengajuan --}}
  <div class="rounded-xl border border-kabut-200 bg-white p-3.5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md sm:rounded-2xl sm:p-5">
    <div class="flex items-center gap-2">
      <span class="text-laut-600">
        <x-ikon n="berkas" class="h-4 w-4" />
      </span>
      <span class="truncate text-xs font-semibold text-kabut-600 sm:text-sm">Total Pengajuan</span>
    </div>
    <div class="mt-2 sm:mt-3">
      <span class="num text-2xl font-extrabold tracking-tight text-tinta sm:text-3xl">{{ $counts['total'] ?? 0 }}</span>
    </div>
    <p class="mt-1 truncate text-2xs text-kabut-500 sm:text-xs">Seluruh permohonan Anda</p>
  </div>

  {{-- Card 2: Pending --}}
  <div class="rounded-xl border border-kabut-200 bg-white p-3.5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md sm:rounded-2xl sm:p-5">
    <div class="flex items-center gap-2">
      <span class="text-surya-600">
        <x-ikon n="jam" class="h-4 w-4" />
      </span>
      <span class="truncate text-xs font-semibold text-kabut-600 sm:text-sm">Pending</span>
    </div>
    <div class="mt-2 sm:mt-3">
      <span class="num text-2xl font-extrabold tracking-tight text-tinta sm:text-3xl">{{ $counts['pending'] ?? 0 }}</span>
    </div>
    <p class="mt-1 truncate text-2xs text-kabut-500 sm:text-xs">Verifikasi & pembayaran</p>
  </div>

  {{-- Card 3: Diproses --}}
  <div class="rounded-xl border border-kabut-200 bg-white p-3.5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md sm:rounded-2xl sm:p-5">
    <div class="flex items-center gap-2">
      <span class="text-laut-600">
        <x-ikon n="lab" class="h-4 w-4" />
      </span>
      <span class="truncate text-xs font-semibold text-kabut-600 sm:text-sm">Diproses</span>
    </div>
    <div class="mt-2 sm:mt-3">
      <span class="num text-2xl font-extrabold tracking-tight text-tinta sm:text-3xl">{{ $counts['diproses'] ?? 0 }}</span>
    </div>
    <p class="mt-1 truncate text-2xs text-kabut-500 sm:text-xs">Pengujian & validasi lab</p>
  </div>

  {{-- Card 4: Selesai --}}
  <div class="rounded-xl border border-kabut-200 bg-white p-3.5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md sm:rounded-2xl sm:p-5">
    <div class="flex items-center gap-2">
      <span class="text-lamun-600">
        <x-ikon n="cek" class="h-4 w-4" />
      </span>
      <span class="truncate text-xs font-semibold text-kabut-600 sm:text-sm">Selesai</span>
    </div>
    <div class="mt-2 sm:mt-3">
      <span class="num text-2xl font-extrabold tracking-tight text-tinta sm:text-3xl">{{ $counts['selesai'] ?? 0 }}</span>
    </div>
    <p class="mt-1 truncate text-2xs text-kabut-500 sm:text-xs">LHP terbit & disahkan</p>
  </div>
</div>

{{-- Kotak Tabel Permohonan Terbaru --}}
<section class="mt-6 overflow-hidden rounded-2xl border border-kabut-200 bg-white shadow-sm">
  <div class="border-b border-kabut-100 px-5 py-4 sm:px-6">
    <h2 class="text-base font-bold text-tinta">Permohonan Terbaru</h2>
    <p class="mt-0.5 text-xs text-kabut-500">Menampilkan 5 aktivitas pengujian terkini Anda</p>
  </div>

  @if ($rows->isEmpty())
    {{-- Empty state: Bersih tanpa logo lab sesuai instruksi --}}
    <div class="px-6 py-12 text-center sm:py-16">
      <h3 class="text-base font-bold text-tinta">Belum ada permohonan</h3>
      <p class="mx-auto mt-2 max-w-[46ch] text-sm text-kabut-600">
        Ajukan permohonan uji, lalu bawa sampel ke loket Balai. Status sampel Anda akan tampil di sini dan diperbarui otomatis.
      </p>
      <div class="mt-5 flex flex-wrap justify-center gap-3">
        <a href="{{ route('permohonan.create') }}" class="btn-primary btn-sm">Mulai pengajuan</a>
        <a href="{{ route('panduan') }}" class="btn-ghost btn-sm">Lihat panduan</a>
      </div>
    </div>
  @else
    {{-- Tabel data dengan kelas tabel-hp agar responsif menjadi kartu di HP tanpa horizontal scroll --}}
    <div class="overflow-x-auto sm:overflow-visible">
      <table class="tabel tabel-hp">
        <thead>
          <tr>
            <th>No. Permohonan</th>
            <th>Total</th>
            <th class="text-right">Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($rows as $r)
            @php
              $isSelesai = $r->status === \App\Enums\Status::Selesai;
              $isPending = in_array($r->status, [
                \App\Enums\Status::Diajukan,
                \App\Enums\Status::Diterima,
                \App\Enums\Status::MenungguBilling,
                \App\Enums\Status::MenungguPembayaran,
                \App\Enums\Status::MenungguKonfirmasiBayar
              ], true);
              $isDitolak = $r->status === \App\Enums\Status::Ditolak;

              if ($isSelesai) {
                  $statusPill = 'bg-lamun-600 text-white';
                  $timePill   = 'bg-lamun-50 text-lamun-700 border border-lamun-100';
              } elseif ($isPending) {
                  $statusPill = 'bg-surya-500 text-white';
                  $timePill   = 'bg-surya-50 text-surya-700 border border-surya-100';
              } elseif ($isDitolak) {
                  $statusPill = 'bg-karang-600 text-white';
                  $timePill   = 'bg-karang-50 text-karang-700 border border-karang-100';
              } else {
                  $statusPill = 'bg-laut-600 text-white';
                  $timePill   = 'bg-laut-50 text-laut-700 border border-laut-100';
              }
            @endphp
            <tr data-href="{{ route('permohonan.show', $r) }}">
              {{-- Kolom No. Permohonan: Pada HP tanpa data-label, otomatis menjadi judul kartu --}}
              <td>
                <a href="{{ route('permohonan.show', $r) }}"
                   class="kode font-semibold text-laut-700 no-underline transition-colors hover:text-laut-600 hover:underline">
                  {{ $r->kodeTampil() }}
                </a>
                <span class="mt-0.5 block text-xs text-kabut-600">
                  {{ $r->nama_sampel }} &middot; {{ $r->jenis_komoditas }} ({{ $r->jumlah_sampel }} sampel)
                </span>
              </td>

              {{-- Kolom Total --}}
              <td data-label="Total">
                <div>
                  <span class="num text-sm font-semibold text-tinta">
                    {{ $r->tagihan > 0 ? rupiah($r->tagihan) : 'Rp 0' }}
                  </span>
                  <span class="block text-2xs text-kabut-500">
                    {{ $r->jenis_bayar->label() }}
                  </span>
                </div>
              </td>

              {{-- Kolom Status --}}
              <td data-label="Status" class="sm:text-right">
                <div class="inline-flex flex-col items-end">
                  <span class="inline-block rounded-full px-3 py-1 text-xs font-semibold leading-none shadow-sm {{ $statusPill }}">
                    {{ $r->status->labelPengguna() }}
                  </span>
                  <span class="mt-1.5 inline-block rounded-full px-2.5 py-0.5 text-2xs font-medium leading-tight num {{ $timePill }}">
                    {{ tanggal($r->updated_at) }}
                  </span>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Footer CTA: Bergaya seperti tautan Masuk auth-inline-link, tanpa panah --}}
    <div class="border-t border-kabut-100 bg-white px-5 py-3.5 text-right sm:px-6">
      <a href="{{ route('permohonan.index') }}" class="auth-inline-link text-sm">
        Lihat selengkapnya
      </a>
    </div>
  @endif
</section>

@endsection
