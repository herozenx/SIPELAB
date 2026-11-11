@props(['p'])
@php
  $S = \App\Enums\Status::class;
  $teks = match (true) {
      $p->status === $S::DiLab && $p->labSaatIni !== null => 'Di Lab '.$p->labSaatIni->nama,
      $p->eksternal() && $p->status === $S::MenungguPembayaran => 'LHU jadi, menunggu pelunasan',
      $p->eksternal() && $p->status === $S::MenungguKonfirmasiBayar => 'Cek pelunasan',
      default => $p->status->label(),
  };
@endphp
<span {{ $attributes->merge(['class' => 'badge-'.$p->status->tone()]) }}>{{ $teks }}</span>
