{{-- Satu kartu operator perusahaan yang bisa dibuka-tutup untuk mengurangi kepadatan form. --}}
@php
  $op = $op ?? [];
  $id = "op-{$i}";
  $e = fn ($f) => is_int($i) ? $errors->first("operator.{$i}.{$f}") : '';
  $no = is_int($i) ? $i + 1 : '';
  $namaLama = trim((string) ($op['nama'] ?? ''));
  $emailLama = trim((string) ($op['email'] ?? ''));
  $adaError = $e('nama') || $e('nik') || $e('email') || $e('telepon') || $e('password');
  $bukaAwal = ! is_int($i) || $adaError || $namaLama === '';
@endphp
<div class="op-card overflow-hidden rounded-xl border border-kabut-200 bg-white" data-ulang data-operator-item>
  <div class="flex items-center gap-1 pr-2">
    <button type="button"
      class="flex min-h-[52px] min-w-0 flex-1 items-center gap-3 px-3.5 py-2.5 text-left transition-colors duration-150 hover:bg-kabut-50"
      aria-expanded="{{ $bukaAwal ? 'true' : 'false' }}"
      aria-controls="{{ $id }}-detail"
      data-operator-toggle>
      <span class="num grid h-7 w-7 shrink-0 place-items-center rounded-full bg-laut-50 text-xs font-bold text-laut-700" aria-hidden="true" data-ulang-no>{{ $no }}</span>
      <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-semibold text-tinta" data-operator-title>Operator {{ $no }}</span>
        <span class="block truncate text-xs text-kabut-600" data-operator-subtitle>{{ $bukaAwal ? 'Isi data akses masuk operator' : ($emailLama !== '' ? $emailLama : 'Ketuk untuk melengkapi data') }}</span>
      </span>
      <x-ikon n="panah-bawah" class="h-4 w-4 shrink-0 text-kabut-600 transition-transform duration-200 {{ $bukaAwal ? 'rotate-180' : '' }}" data-operator-chevron />
    </button>

    <button type="button"
      class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-karang-600 transition-colors duration-150 hover:bg-karang-50 hover:text-karang-700"
      data-ulang-hapus
      aria-label="Hapus operator {{ $no }}"
      title="Hapus operator">
      <x-ikon n="hapus" class="h-[18px] w-[18px]" />
    </button>
  </div>

  <div id="{{ $id }}-detail" class="op-panel {{ $bukaAwal ? '' : 'is-closed' }}" data-operator-detail>
    <div class="op-panel-inner border-t border-kabut-100 bg-kabut-50">
      <div class="grid gap-3 p-3.5 sm:grid-cols-2">
        <div data-field>
          <label for="{{ $id }}-nama" class="label">Nama lengkap <span class="text-karang-600" aria-hidden="true">*</span><span class="sr-only"> wajib</span></label>
          <div class="relative">
            <input id="{{ $id }}-nama" name="operator[{{ $i }}][nama]" value="{{ $op['nama'] ?? '' }}"
              class="form-input input min-h-[44px] pr-10" maxlength="100" required autocomplete="off"
              data-operator-nama
              @if($e('nama')) aria-invalid="true" data-server-error="{{ $e('nama') }}" @endif>
            <span data-error-icon="{{ $id }}-nama" class="field-error-icon hidden" aria-hidden="true"><x-ikon n="peringatan" class="h-5 w-5" /></span>
          </div>
          <p data-field-error="{{ $id }}-nama" class="auth-field-error hidden"></p>
        </div>

        <div data-field>
          <label for="{{ $id }}-nik" class="label">NIK (16 angka) <span class="text-karang-600" aria-hidden="true">*</span><span class="sr-only"> wajib</span></label>
          <div class="relative">
            <input id="{{ $id }}-nik" name="operator[{{ $i }}][nik]" value="{{ $op['nik'] ?? '' }}"
              class="form-input input num min-h-[44px] pr-10" inputmode="numeric" maxlength="16" pattern="\d{16}" required autocomplete="off"
              @if($e('nik')) aria-invalid="true" data-server-error="{{ $e('nik') }}" @endif>
            <span data-error-icon="{{ $id }}-nik" class="field-error-icon hidden" aria-hidden="true"><x-ikon n="peringatan" class="h-5 w-5" /></span>
          </div>
          <p data-field-error="{{ $id }}-nik" class="auth-field-error hidden"></p>
        </div>

        <div data-field>
          <label for="{{ $id }}-email" class="label">Email<span class="text-karang-600" aria-hidden="true">*</span><span class="sr-only"> wajib</span></label>
          <div class="relative">
            <input id="{{ $id }}-email" name="operator[{{ $i }}][email]" type="email" value="{{ $op['email'] ?? '' }}"
              class="form-input input min-h-[44px] pr-10" maxlength="120" required autocomplete="off"
              data-operator-email
              @if($e('email')) aria-invalid="true" data-server-error="{{ $e('email') }}" @endif>
            <span data-error-icon="{{ $id }}-email" class="field-error-icon hidden" aria-hidden="true"><x-ikon n="peringatan" class="h-5 w-5" /></span>
          </div>
          <p data-field-error="{{ $id }}-email" class="auth-field-error hidden"></p>
        </div>

        <div data-field>
          <label for="{{ $id }}-telepon" class="label">No. HP <span class="font-normal text-kabut-600">(opsional)</span></label>
          <div data-phone-box class="relative flex min-h-[44px] overflow-hidden rounded-lg border border-kabut-300 bg-white focus-within:border-laut-500 focus-within:ring-1 focus-within:ring-laut-500">
            <span class="flex shrink-0 items-center border-r border-kabut-200 bg-kabut-50 px-3 text-sm font-semibold text-tinta" aria-label="Kode negara Indonesia +62">+62</span>
            <input id="{{ $id }}-telepon" name="operator[{{ $i }}][telepon]" type="tel" value="{{ preg_replace('/^\+?62/', '', (string) ($op['telepon'] ?? '')) }}"
              class="input num min-h-[44px] min-w-0 border-0 bg-white px-3 pr-10 shadow-none focus:shadow-none" inputmode="tel" maxlength="20" autocomplete="off"
              @if($e('telepon')) aria-invalid="true" data-server-error="{{ $e('telepon') }}" @endif>
            <span data-error-icon="{{ $id }}-telepon" class="field-error-icon !right-3 hidden" aria-hidden="true"><x-ikon n="peringatan" class="h-5 w-5" /></span>
          </div>
          <p data-field-error="{{ $id }}-telepon" class="auth-field-error hidden"></p>
        </div>

        <div class="sm:col-span-2" data-field>
          <label for="{{ $id }}-password" class="label">Kata sandi operator <span class="text-karang-600" aria-hidden="true">*</span><span class="sr-only"> wajib</span></label>
          <div class="relative">
            <input id="{{ $id }}-password" name="operator[{{ $i }}][password]" type="password"
              class="form-input input min-h-[44px] pr-20" maxlength="128" required autocomplete="new-password"
              aria-describedby="{{ $id }}-password-help" data-password-input
              @if($e('password')) aria-invalid="true" data-server-error="{{ $e('password') }}" @endif>
            <span data-error-icon="{{ $id }}-password" class="field-error-icon !right-12 hidden" aria-hidden="true"><x-ikon n="peringatan" class="h-5 w-5" /></span>
            <button type="button"
              class="absolute inset-y-0 right-0 min-h-[44px] min-w-[44px] px-3 text-kabut-600 transition-colors duration-150 hover:text-tinta"
              data-toggle-password="{{ $id }}-password"
              aria-label="Tampilkan kata sandi operator"
              aria-controls="{{ $id }}-password">
              <span data-eye-open><x-ikon n="mata" class="h-5 w-5" /></span>
              <span data-eye-closed class="hidden"><x-ikon n="mata-tutup" class="h-5 w-5" /></span>
            </button>
          </div>
          @include('auth._password-checklist', ['for' => "{$id}-password", 'id' => "{$id}-password-help"])
          <p data-field-error="{{ $id }}-password" class="auth-field-error hidden"></p>
        </div>
      </div>
    </div>
  </div>
</div>
