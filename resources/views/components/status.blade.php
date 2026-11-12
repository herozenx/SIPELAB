@props(['p'])
@php
  $S = \App\Enums\Status::class;
  // Pengguna jasa tidak melihat tahapan internal lab: cukup "Sedang diproses".
  $penggunaJasa = auth()->user()?->role === \App\Enums\Role::PenggunaJasa;
  $teks = match (true) {
      $penggunaJasa && in_array($p->status, [$S::DiLab, $S::MenungguValidasi], true) => 'Sedang diproses',
      $p->status === $S::DiLab && $p->labSaatIni !== null => 'Di Lab '.$p->labSaatIni->nama,
      $p->eksternal() && $p->status === $S::MenungguPembayaran => 'LHU jadi, menunggu pelunasan',
      $p->eksternal() && $p->status === $S::MenungguKonfirmasiBayar => 'Cek pelunasan',
      default => $p->status->label(),
  };
  $nada = $penggunaJasa && $p->status === $S::MenungguValidasi ? 'lab' : $p->status->tone();
@endphp
<span {{ $attributes->merge(['class' => 'badge-'.$nada]) }}>{{ $teks }}</span>
