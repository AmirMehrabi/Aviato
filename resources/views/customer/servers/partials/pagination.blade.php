@if ($paginator->hasPages())
    <nav role="navigation" aria-label="صفحه‌بندی ماشین‌ها" class="flex flex-col items-center gap-4">
        <p class="text-sm font-black text-slate-950">نمایش {{ $paginator->firstItem() ?? 0 }} تا {{ $paginator->lastItem() ?? 0 }} از {{ $paginator->total() }} ماشین</p>
        <div class="flex w-full flex-wrap items-center justify-center gap-2">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border-2 border-slate-200 bg-white px-4 text-sm font-black text-slate-400">صفحه قبل</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border-2 border-[#0069FF] bg-white px-4 text-sm font-black text-[#0069FF] transition-colors hover:bg-[#DCEBFF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    صفحه قبل
                </a>
            @endif

            <div class="hidden flex-wrap items-center justify-center gap-2 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="inline-flex size-12 items-center justify-center text-base font-black text-slate-500">{{ $element }}</span>
                    @else
                        @foreach ($element as $page => $url)
                            @if ($page === $paginator->currentPage())
                                <span aria-current="page" aria-label="صفحه {{ $page }}" class="inline-flex size-12 items-center justify-center rounded-xl bg-[#0069FF] text-base font-black text-white shadow-lg shadow-[#0069FF]/20">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" aria-label="صفحه {{ $page }}" class="inline-flex size-12 items-center justify-center rounded-xl border-2 border-[#B8D6FF] bg-white text-base font-black text-[#0069FF] transition-colors hover:border-[#0069FF] hover:bg-[#DCEBFF] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            <span class="px-2 text-sm font-black text-slate-700 sm:hidden">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-[#0069FF] px-4 text-sm font-black text-white shadow-lg shadow-[#0069FF]/20 transition-colors hover:bg-[#0050D0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0069FF]">
                    صفحه بعد
                    <svg class="size-4 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            @else
                <span aria-disabled="true" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border-2 border-slate-200 bg-white px-4 text-sm font-black text-slate-400">صفحه بعد</span>
            @endif
        </div>
    </nav>
@endif
