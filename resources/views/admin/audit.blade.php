@extends('layouts.app')
@section('title', 'Log aktivitas')
@section('content')
<h1>Log aktivitas</h1>
<p class="mt-1 text-kabut-600">Jejak audit: siapa melakukan apa, kapan, dan dari alamat IP mana.</p>
<form method="get" class="mt-6 flex gap-3">
  <label for="aksi" class="sr-only">Jenis aktivitas</label>
  <select id="aksi" name="aksi" class="input sm:w-72" data-autosubmit>
    <option value="">Semua aktivitas</option>
    @foreach ($aksiList as $a)<option value="{{ $a }}" @selected($aksi === $a)>{{ ucfirst(strtolower(str_replace('_', ' ', $a))) }}</option>@endforeach
  </select>
  <button class="btn-ghost">Terapkan</button>
</form>
<section class="panel mt-4 overflow-hidden">
  <div class="overflow-x-auto">
  <table class="tabel tabel-hp">
    <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Objek</th><th>IP</th></tr></thead>
    <tbody>
    @forelse ($rows as $r)
      <tr>
        <td class="num whitespace-nowrap text-kabut-500">{{ tanggal($r->created_at) }}</td>
        <td data-label="Pengguna"><span>{{ $r->user?->nama ?? 'Tamu' }}@if($r->user)<span class="block text-xs text-kabut-500">{{ $r->user->email }}</span>@endif</span></td>
        <td data-label="Aktivitas"><span class="font-semibold {{ str_contains($r->action, 'GAGAL') || str_contains($r->action, 'KUNCI') ? 'text-karang-600' : '' }}">{{ ucfirst(strtolower(str_replace('_', ' ', $r->action))) }}</span>
          @if ($r->detail)<span class="block max-w-[340px] truncate text-xs text-kabut-500" title="{{ $r->detail }}">{{ $r->detail }}</span>@endif</td>
        <td class="whitespace-nowrap" data-label="Objek">@if($r->entity === 'permohonan' && $r->entity_id)<a href="{{ route('permohonan.show', $r->entity_id) }}">Permohonan #{{ $r->entity_id }}</a>@elseif($r->entity){{ ucfirst($r->entity) }}@if($r->entity_id) #{{ $r->entity_id }}@endif @else – @endif</td>
        <td class="num whitespace-nowrap text-kabut-500" data-label="IP">{{ $r->ip }}</td>
      </tr>
    @empty
      <tr><td colspan="5" class="py-10 text-center text-kabut-500">Belum ada aktivitas.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
  {{ $rows->links() }}
</section>
@endsection
