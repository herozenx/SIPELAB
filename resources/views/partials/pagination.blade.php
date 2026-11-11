@if ($paginator->hasPages())
<nav class="flex items-center justify-between gap-3 border-t border-kabut-200 px-4 py-3 text-sm" aria-label="Halaman">
  <p class="text-kabut-500">Menampilkan <span class="num font-semibold text-tinta">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span> dari <span class="num font-semibold text-tinta">{{ $paginator->total() }}</span></p>
  <div class="flex gap-2">
    @if ($paginator->onFirstPage())<span class="btn-ghost btn-sm opacity-50">Sebelumnya</span>@else<a class="btn-ghost btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>@endif
    @if ($paginator->hasMorePages())<a class="btn-ghost btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>@else<span class="btn-ghost btn-sm opacity-50">Berikutnya</span>@endif
  </div>
</nav>
@endif
