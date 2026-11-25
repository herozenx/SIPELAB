{{-- Satu blok akun operator perusahaan. $i = indeks (angka, atau "__i__" untuk template); $op = isian lama. --}}
@php
  $op = $op ?? [];
  $id = "op-{$i}";
  $e = fn ($f) => is_int($i) ? $errors->first("operator.{$i}.{$f}") : '';
@endphp
<div class="rounded-lg border border-kabut-200 bg-kabut-50 p-3" data-ulang>
  <div class="flex items-center justify-between gap-2">
    <p class="text-sm font-bold">Operator <span data-ulang-no>{{ is_int($i) ? $i + 1 : '' }}</span></p>
    <button type="button" class="btn-ghost btn-sm" data-ulang-hapus><x-ikon n="silang" class="h-4 w-4" />Hapus</button>
  </div>
  <div class="mt-2 grid gap-3 sm:grid-cols-2">
    <div>
      <label for="{{ $id }}-nama" class="label">Nama lengkap</label>
      <input id="{{ $id }}-nama" name="operator[{{ $i }}][nama]" value="{{ $op['nama'] ?? '' }}" class="input" maxlength="100" required autocomplete="off" @if($e('nama')) aria-invalid="true" @endif>
      @if($e('nama'))<p class="error">{{ $e('nama') }}</p>@endif
    </div>
    <div>
      <label for="{{ $id }}-nik" class="label">NIK</label>
      <input id="{{ $id }}-nik" name="operator[{{ $i }}][nik]" value="{{ $op['nik'] ?? '' }}" class="input num" inputmode="numeric" maxlength="16" pattern="\d{16}" required autocomplete="off" @if($e('nik')) aria-invalid="true" @endif>
      @if($e('nik'))<p class="error">{{ $e('nik') }}</p>@endif
    </div>
    <div>
      <label for="{{ $id }}-email" class="label">Email <span class="font-normal text-kabut-500">(untuk masuk)</span></label>
      <input id="{{ $id }}-email" name="operator[{{ $i }}][email]" type="email" value="{{ $op['email'] ?? '' }}" class="input" maxlength="120" required autocomplete="off" @if($e('email')) aria-invalid="true" @endif>
      @if($e('email'))<p class="error">{{ $e('email') }}</p>@endif
    </div>
    <div>
      <label for="{{ $id }}-telepon" class="label">No. HP <span class="font-normal text-kabut-500">(opsional)</span></label>
      <input id="{{ $id }}-telepon" name="operator[{{ $i }}][telepon]" type="tel" value="{{ $op['telepon'] ?? '' }}" class="input num" inputmode="tel" maxlength="20" autocomplete="off" @if($e('telepon')) aria-invalid="true" @endif>
      @if($e('telepon'))<p class="error">{{ $e('telepon') }}</p>@endif
    </div>
    <div class="sm:col-span-2">
      <label for="{{ $id }}-password" class="label">Kata sandi operator</label>
      <input id="{{ $id }}-password" name="operator[{{ $i }}][password]" type="password" class="input" maxlength="128" required autocomplete="new-password" @if($e('password')) aria-invalid="true" @endif>
      <p class="hint">Minimal 8 karakter, huruf besar, huruf kecil, dan angka.</p>
      @if($e('password'))<p class="error">{{ $e('password') }}</p>@endif
    </div>
  </div>
</div>
