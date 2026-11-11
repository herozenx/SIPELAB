@foreach (['success' => 'cek', 'error' => 'silang', 'warning' => 'info'] as $jenis => $ikon)
  @if (session($jenis))
    <div class="alert-{{ $jenis }} mb-5" role="{{ $jenis === 'error' ? 'alert' : 'status' }}"><x-ikon :n="$ikon" class="mt-0.5 h-4 w-4 shrink-0" /><div>{{ session($jenis) }}</div></div>
  @endif
@endforeach
@if ($errors->any() && ! ($sembunyikanError ?? false))
  <div class="alert-error mb-5" role="alert"><x-ikon n="silang" class="mt-0.5 h-4 w-4 shrink-0" />
    <div>@if ($errors->count() === 1){{ $errors->first() }}@else<p class="font-semibold">Periksa kembali isian berikut:</p><ul class="mt-1 list-disc pl-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif</div>
  </div>
@endif
