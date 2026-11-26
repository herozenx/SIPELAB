{{-- Overlay loading ringan berlogo KKP saat formulir Masuk/Daftar sedang dikirim ke server. --}}
<div class="auth-loading fixed inset-0 z-50 flex items-center justify-center bg-tinta/40 p-4" role="status" aria-live="polite" aria-hidden="true" data-auth-loading>
  <div class="auth-loading-panel w-full max-w-[220px] rounded-2xl border border-kabut-200 bg-white px-6 py-6 text-center shadow-[0_22px_50px_rgba(11,61,80,0.20)]">
    <div class="relative mx-auto flex h-20 w-20 items-center justify-center">
      <span class="auth-loading-ring absolute inset-0 rounded-full border-2 border-laut-100 border-t-laut-600" aria-hidden="true"></span>
      <span class="flex h-14 w-14 items-center justify-center rounded-full bg-laut-50">
        <img src="{{ asset('img/logo-kkp.png') }}" alt="" width="40" height="36" class="h-9 w-auto object-contain">
      </span>
    </div>
    <p class="mt-3 text-sm font-semibold text-tinta">Loading</p>
  </div>
</div>
