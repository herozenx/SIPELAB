{{-- Daftar ringkas syarat kata sandi sesuai aturan backend (min 8, huruf besar & kecil, angka). --}}
<ul id="{{ $id }}" class="mt-2 space-y-1 text-xs" data-password-checklist="{{ $for }}">
  @foreach ([
    'len' => 'Minimal 8 karakter',
    'case' => 'Kombinasi huruf kapital & huruf kecil',
    'num' => 'Minimal 1 angka',
  ] as $key => $text)
    <li class="flex items-center gap-1.5 text-karang-600 transition-colors duration-150" data-pw-rule="{{ $key }}">
      <x-ikon n="silang" class="h-3.5 w-3.5 shrink-0" data-pw-pending />
      <x-ikon n="cek" class="hidden h-3.5 w-3.5 shrink-0" data-pw-ok />
      <span>{{ $text }}</span>
    </li>
  @endforeach
</ul>
