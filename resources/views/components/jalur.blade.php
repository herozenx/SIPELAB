@props(['p', 'labs'])
@php $st = \App\Support\Jalur::untuk($p, $labs); @endphp
<ol class="jalur" data-n="{{ min(count($st), 10) }}" aria-label="Jalur uji sampel">
  @foreach ($st as $i => $s)
    <li class="jalur-stasiun" data-state="{{ $s['state'] }}" @if($s['state'] === 'now') aria-current="step" @endif>
      <span class="jalur-titik">
        @if ($s['state'] === 'done')<x-ikon n="cek" class="h-4 w-4" />
        @elseif ($s['state'] === 'fail')<x-ikon n="silang" class="h-4 w-4" />
        @else{{ $i + 1 }}@endif
      </span>
      <span class="jalur-nama">{{ $s['nama'] }}</span>
      <span class="jalur-sub">{{ $s['sub'] }}</span>
    </li>
  @endforeach
</ol>
