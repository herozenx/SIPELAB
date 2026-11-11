@extends('layouts.app')
@section('title', 'Antrean lab')
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1>Antrean Lab {{ $u->lab?->nama ?? '—' }}</h1>
    <p class="mt-1 text-kabut-600">Sampel dengan batas norma waktu terdekat ada di urutan teratas. Norma Lab {{ $u->lab?->nama }}: {{ $u->lab?->norma_hari }} hari kerja. Anda sudah menginput {{ $hariIni }} hasil hari ini.</p>
  </div>
  <a href="{{ route('riwayat') }}" class="btn-ghost"><x-ikon n="riwayat" class="h-4 w-4" />Riwayat uji</a>
</div>

@unless ($u->lab)
  <div class="alert-warning mt-6"><x-ikon n="info" class="mt-0.5 h-4 w-4 shrink-0" />Akun Anda belum ditempatkan di laboratorium mana pun. Hubungi Superadmin.</div>
@endunless

<section class="panel mt-6 overflow-hidden">
  @if ($antrean->isEmpty())
    <div class="px-6 py-14 text-center">
      <p class="font-semibold">Antrean kosong</p>
      <p class="mt-1 text-sm text-kabut-500">Sampel baru masuk otomatis setelah pembayaran dikonfirmasi atau lab sebelumnya selesai menguji. Halaman ini diperbarui sendiri.</p>
    </div>
  @else
    <div class="overflow-x-auto">
    <table class="tabel tabel-hp">
      <thead><tr><th>No.</th><th>Kode</th><th>Sampel</th><th>Jumlah</th><th>Norma waktu</th><th>Ditangani</th><th></th></tr></thead>
      <tbody>
        @foreach ($antrean as $i => $r)
          @php $t = $r->tahapAktif; @endphp
          <tr data-href="{{ route('permohonan.show', $r) }}">
            <td class="num text-kabut-500 hp-sembunyi">{{ $i + 1 }}</td>
            <td class="kode"><span class="sm:hidden text-kabut-500">{{ $i + 1 }}. </span>{{ $r->kode }}</td>
            <td><span class="font-semibold">{{ $r->nama_sampel }}</span><span class="block text-xs text-kabut-500">{{ $r->jenis_komoditas }}, asal {{ $r->asal_sampel }}</span></td>
            <td class="num" data-label="Jumlah">{{ $r->jumlah_sampel }} sampel</td>
            <td class="whitespace-nowrap" data-label="Norma waktu">
              <span>
                @if ($t)
                  @php [$nTeks, $nNada] = $t->norma(); @endphp
                  <span class="badge-{{ $nNada }}">{{ $nTeks }}</span>
                  <span class="mt-1 block text-xs text-kabut-500">Batas {{ tanggal($t->batas_at, false) }}</span>
                @else
                  <span class="text-xs text-kabut-500" title="{{ tanggal($r->updated_at) }}">Masuk {{ $r->updated_at->locale('id')->diffForHumans() }}</span>
                @endif
              </span>
            </td>
            <td class="min-w-[120px] text-xs" data-label="Ditangani">
              @if ($t && $t->operator->isNotEmpty())
                <span>{{ $t->operator->pluck('nama')->join(', ') }}</span>
              @else
                <span class="text-kabut-500">Belum ada</span>
              @endif
            </td>
            <td class="text-right max-sm:!pt-2"><a href="{{ route('permohonan.show', $r) }}" class="btn-primary btn-sm max-sm:w-full">{{ $t && $t->operator->contains('id', $u->id) ? 'Input hasil' : 'Buka' }}</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
    </div>
  @endif
</section>
<p class="mt-3 text-xs text-kabut-500">Uji dilakukan independen: identitas pemohon, tagihan, dan hasil lab lain tidak ditampilkan kepada petugas.</p>
@endsection
