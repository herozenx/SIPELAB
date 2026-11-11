@extends('layouts.app')
@section('title', 'Chat')
@section('tanpa-poll', '1')
@section('content')
@php $balai = $u->isPetugasBalai(); @endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1>{{ $balai ? 'Chat pengguna jasa' : 'Chat dengan petugas Balai' }}</h1>
    <p class="mt-1 max-w-prose text-kabut-600">
      @if ($balai)
        Semua admin dan operator lab bisa membaca dan membalas. Nama dan jabatan Anda tampil di setiap balasan.
      @else
        Tanyakan apa saja tentang permohonan, pembayaran, atau hasil uji. Pesan dibalas oleh admin atau operator lab yang sedang bertugas.
      @endif
    </p>
  </div>
  @unless ($balai)<a href="{{ route('panduan') }}" class="btn-ghost"><x-ikon n="buku" class="h-4 w-4" />Panduan</a>@endunless
</div>

<div class="mt-6 grid grid-cols-1 gap-6 {{ $balai ? 'lg:grid-cols-[320px_minmax(0,1fr)]' : 'lg:grid-cols-[minmax(0,1fr)_280px]' }}">
  @if ($balai)
    {{-- Kotak masuk: daftar percakapan --}}
    <aside class="panel flex max-h-[75vh] flex-col overflow-hidden" data-chat-inbox>
      <form method="get" class="border-b border-kabut-200 p-3">
        <label for="cari-chat" class="sr-only">Cari percakapan</label>
        <input id="cari-chat" name="cari" value="{{ $cari ?? '' }}" class="input" placeholder="Cari nama atau instansi">
      </form>
      <ul class="flex-1 divide-y divide-kabut-100 overflow-y-auto">
        @forelse ($threads as $t)
          @php $n = (int) ($belumPer[$t->id] ?? 0); $aktif = $thread && $thread->id === $t->id; @endphp
          <li>
            <a href="{{ route('chat', ['t' => $t->id] + (isset($cari) && $cari !== '' ? ['cari' => $cari] : [])) }}" class="block px-4 py-3 text-tinta no-underline hover:bg-kabut-50 hover:no-underline {{ $aktif ? 'bg-laut-50' : '' }}" @if($aktif) aria-current="true" @endif>
              <span class="flex items-center justify-between gap-2">
                <span class="flex min-w-0 items-center gap-2"><span class="h-2 w-2 shrink-0 rounded-full {{ $t->pengguna?->online() ? 'bg-lamun-500' : 'bg-kabut-300' }}"></span><span class="truncate text-sm font-semibold">{{ $t->pengguna?->nama }}</span></span>
                @if ($n)<span class="num shrink-0 rounded-full bg-karang-600 px-2 py-0.5 text-2xs font-bold text-white">{{ $n }}</span>@endif
              </span>
              <span class="block truncate text-xs text-kabut-500">{{ $t->pengguna?->instansi }}</span>
              <span class="mt-0.5 block truncate text-xs {{ $n ? 'font-semibold text-tinta' : 'text-kabut-500' }}">{{ $t->pesanTerakhir?->body }}</span>
            </a>
          </li>
        @empty
          <li class="px-4 py-10 text-center text-sm text-kabut-500">Belum ada percakapan{{ isset($cari) && $cari !== '' ? ' yang cocok' : '' }}.</li>
        @endforelse
      </ul>
    </aside>
  @endif

  @if ($thread)
    <section class="panel flex h-[75vh] min-h-[420px] flex-col overflow-hidden" data-chat
             data-url-pesan="{{ route('chat.pesan', $thread) }}" data-url-kirim="{{ route('chat.kirim', $thread) }}">
      <div class="panel-head">
        @if ($balai)
          <div class="min-w-0">
            <h2 class="truncate">{{ $thread->pengguna?->nama }}</h2>
            <p class="truncate text-xs text-kabut-500">{{ $thread->pengguna?->instansi }} · {{ $thread->pengguna?->jenisBayar()->label() }} · <span data-chat-pengguna-online>{{ $thread->pengguna?->online() ? 'online' : 'offline' }}</span></p>
          </div>
          @if ($u->isStaff())
            <a href="{{ route('permohonan.index', ['cari' => $thread->pengguna?->instansi ?: $thread->pengguna?->nama]) }}" class="btn-ghost btn-sm shrink-0">Permohonannya</a>
          @endif
        @else
          <div><h2>Petugas Balai</h2><p class="text-xs text-kabut-500">Jam layanan Senin–Jumat. Balasan di luar jam kerja bisa lebih lama.</p></div>
        @endif
      </div>

      <div class="flex-1 space-y-3 overflow-y-auto bg-kabut-50 px-4 py-4" data-chat-list aria-live="polite" aria-label="Isi percakapan">
        <p class="py-10 text-center text-sm text-kabut-500" data-chat-kosong>{{ $balai ? 'Memuat percakapan…' : 'Belum ada pesan. Tulis pertanyaan Anda di bawah.' }}</p>
      </div>

      <form class="border-t border-kabut-200 p-3" data-chat-form>
        <label for="isi-chat" class="sr-only">Tulis pesan</label>
        <div class="flex items-end gap-2">
          <textarea id="isi-chat" name="isi" rows="2" maxlength="2000" class="input resize-none" placeholder="Tulis pesan… (Enter untuk kirim, Shift+Enter untuk baris baru)" required></textarea>
          <button class="btn-primary shrink-0 py-3" data-chat-kirim><x-ikon n="kirim" class="h-4 w-4" /><span class="hidden sm:inline">Kirim</span></button>
        </div>
        <p class="error hidden" data-chat-error></p>
      </form>
    </section>
  @else
    <section class="panel grid place-items-center px-6 py-20 text-center">
      <div><p class="font-semibold">Belum ada percakapan</p><p class="mt-1 text-sm text-kabut-500">Pesan dari pengguna jasa akan muncul di sini.</p></div>
    </section>
  @endif

  @unless ($balai)
    <aside class="space-y-6">
      <section class="panel">
        <div class="panel-head"><h2>Petugas online</h2></div>
        <ul class="panel-body space-y-3" data-chat-online>
          @forelse ($online as $o)
            <li class="flex items-center gap-3"><span class="relative grid h-8 w-8 shrink-0 place-items-center rounded-full bg-laut-100 text-xs font-bold text-laut-700">{{ $o['inisial'] }}<span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-lamun-500 ring-2 ring-white"></span></span><span class="min-w-0 leading-tight"><span class="block truncate text-sm font-semibold">{{ $o['nama'] }}</span><span class="block truncate text-xs text-kabut-500">{{ $o['jabatan'] }}</span></span></li>
          @empty
            <li class="text-sm text-kabut-500">Belum ada petugas online. Pesan Anda tetap tersimpan dan akan dibalas.</li>
          @endforelse
        </ul>
      </section>
      @if ($wa = wa_link('Halo Balai, saya '.$u->nama.($u->instansi ? ' ('.$u->instansi.')' : '').'. Saya ingin bertanya tentang layanan uji SIPELAB.'))
        <section class="panel panel-body text-sm">
          <p class="font-semibold">Butuh jawaban cepat di luar chat?</p>
          <p class="mt-1 text-kabut-600">Hubungi WhatsApp Center Balai pada jam layanan.</p>
          <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn-ghost btn-sm mt-3"><x-ikon n="chat" class="h-4 w-4" />Chat WhatsApp</a>
        </section>
      @endif
      <section class="panel panel-body text-sm text-kabut-600">
        <p class="font-semibold text-tinta">Tips</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
          <li>Sebutkan kode permohonan (mis. PMH-2026-00001) agar cepat ditangani.</li>
          <li>Jangan kirim kata sandi atau data rahasia lewat chat.</li>
        </ul>
      </section>
    </aside>
  @endunless
</div>

@if ($balai)
  <section class="panel mt-6">
    <div class="panel-head"><h2>Rekan yang sedang online</h2><span class="text-xs text-kabut-500">Diperbarui otomatis</span></div>
    <ul class="panel-body flex flex-wrap gap-2" data-chat-online data-chat-online-chip>
      @forelse ($online as $o)
        <li class="inline-flex items-center gap-2 rounded-full bg-kabut-100 py-1 pl-1 pr-3 text-xs"><span class="grid h-6 w-6 place-items-center rounded-full bg-laut-600 text-2xs font-bold text-white">{{ $o['inisial'] }}</span><span><span class="font-semibold">{{ $o['nama'] }}</span> · {{ $o['jabatan'] }}</span></li>
      @empty
        <li class="text-sm text-kabut-500">Tidak ada.</li>
      @endforelse
    </ul>
  </section>
@endif
@endsection
