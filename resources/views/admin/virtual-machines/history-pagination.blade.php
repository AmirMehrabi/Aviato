@if($paginator->hasPages())
<nav aria-label="صفحه‌بندی {{ $label }}" class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 text-sm">
    @if($paginator->onFirstPage())
        <span aria-disabled="true" class="px-3 py-3 text-slate-400">قبلی</span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="min-h-11 rounded-lg border border-slate-300 px-3 py-3 font-bold text-blue-700">قبلی</a>
    @endif
    <span>صفحه {{ $paginator->currentPage() }} از {{ $paginator->lastPage() }}</span>
    @if($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="min-h-11 rounded-lg border border-slate-300 px-3 py-3 font-bold text-blue-700">بعدی</a>
    @else
        <span aria-disabled="true" class="px-3 py-3 text-slate-400">بعدی</span>
    @endif
</nav>
@endif
