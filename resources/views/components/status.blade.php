@props(['p'])
@php
  $S = \App\Enums\Status::class;
  // Pengguna jasa tidak melihat tahapan internal lab: cukup "Sedang diproses/pengujian".
  $penggunaJasa = auth()->user()?->role === \App\Enums\Role::PenggunaJasa;
  $pelunasan = $p->status->tahapBayar() && $p->relationLoaded('lhu') && $p->lhu?->nomor;
  $teks = $penggunaJasa ? $p->status->labelPengguna() : $p->status->label();
  if ($pelunasan) {
      $teks = 'LHP selesai · '.mb_strtolower($teks);
  }
  $nada = $penggunaJasa && $p->status->tahapLhp() ? 'lab' : $p->status->tone();
@endphp
<span {{ $attributes->merge(['class' => 'badge-'.$nada]) }}>{{ $teks }}</span>
