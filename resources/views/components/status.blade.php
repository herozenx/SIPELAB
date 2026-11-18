@props(['p'])
@php
  $S = \App\Enums\Status::class;
  // Pengguna jasa tidak melihat tahapan internal Balai: cukup "Sedang diproses".
  $penggunaJasa = auth()->user()?->role === \App\Enums\Role::PenggunaJasa;
  $teks = match (true) {
      $penggunaJasa && $p->status->internalLab() => 'Sedang diproses',
      $p->status === $S::DiLab && $p->labSaatIni !== null => 'Di Lab '.$p->labSaatIni->nama.($p->dikembalikan ? ' (perbaikan)' : ''),
      $p->eksternal() && $p->status === $S::MenungguPembayaran => 'LHP jadi, menunggu pelunasan',
      $p->eksternal() && $p->status === $S::MenungguKonfirmasiBayar => 'Cek pelunasan',
      default => $p->status->label(),
  };
  $nada = $penggunaJasa && $p->status->internalLab() ? 'lab' : $p->status->tone();
@endphp
<span {{ $attributes->merge(['class' => 'badge-'.$nada]) }}>{{ $teks }}</span>
