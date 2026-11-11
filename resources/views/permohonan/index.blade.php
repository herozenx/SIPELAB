@extends('layouts.app')
@section('title', 'Permohonan')
@section('content')
@php $staff = auth()->user()->isStaff(); @endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1>{{ $staff ? 'Semua permohonan' : 'Permohonan saya' }}</h1>
    <p class="mt-1 text-kabut-600">{{ $rows->total() }} permohonan{{ $status ? ' berstatus "'.$status->label().'"' : '' }}{{ $cari !== '' ? ' cocok dengan "'.$cari.'"' : '' }}.</p>
  </div>
  @unless ($staff)<a href="{{ route('permohonan.create') }}" class="btn-primary"><x-ikon n="tambah" class="h-4 w-4" />Ajukan uji baru</a>@endunless
</div>

<form method="get" class="mt-6 flex flex-col gap-3 sm:flex-row" role="search">
  <div class="relative flex-1">
    <label for="cari" class="sr-only">Cari</label>
    <x-ikon n="cari" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-kabut-400" />
    <input id="cari" name="cari" value="{{ $cari }}" class="input pl-9" placeholder="{{ $staff ? 'Cari kode, sampel, komoditas, atau pemohon' : 'Cari kode, sampel, atau komoditas' }}">
  </div>
  <label for="status" class="sr-only">Status</label>
  <select id="status" name="status" class="input sm:w-56" data-autosubmit>
    <option value="">Semua status</option>
    @foreach (\App\Enums\Status::cases() as $s)
      <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>
    @endforeach
  </select>
  <button class="btn-ghost">Terapkan</button>
</form>

<section class="panel mt-4 overflow-hidden">
  @if ($rows->isEmpty())
    <div class="px-6 py-14 text-center">
      <p class="font-semibold">Tidak ada permohonan yang cocok</p>
      <p class="mt-1 text-sm text-kabut-500">@if($cari !== '' || $status)Ubah kata kunci atau <a href="{{ route('permohonan.index') }}">hapus filter</a>.@elseif(!$staff)<a href="{{ route('permohonan.create') }}">Ajukan permohonan uji pertama Anda</a>.@else Permohonan dari pengguna jasa akan muncul di sini.@endif</p>
    </div>
  @else
    @include('partials.tabel-permohonan', ['rows' => $rows, 'labs' => $labs, 'pemohon' => $staff])
    {{ $rows->links() }}
  @endif
</section>
@endsection
