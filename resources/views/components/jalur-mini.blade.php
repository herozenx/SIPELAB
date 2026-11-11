@props(['p', 'labs'])
@php $st = \App\Support\Jalur::untuk($p, $labs); @endphp
<span class="jalur-mini" title="{{ collect($st)->firstWhere('state', 'now')['nama'] ?? ($p->status === \App\Enums\Status::Selesai ? 'Selesai' : '') }}" aria-hidden="true">
  @foreach ($st as $i => $s)
    @if ($i > 0)<b data-state="{{ in_array($s['state'], ['done', 'now', 'fail']) ? 'done' : 'todo' }}"></b>@endif
    <i data-state="{{ $s['state'] }}"></i>
  @endforeach
</span>
