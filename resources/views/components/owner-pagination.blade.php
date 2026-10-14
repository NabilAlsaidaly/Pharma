@props(['paginator'])
@if ($paginator->total() > 0)
    <nav aria-label="صفحات النتائج" class="mt-5 flex flex-wrap items-center justify-between gap-4 text-sm">
        <p class="text-xs text-stone-600">عرض <bdi dir="ltr">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</bdi> من
            <bdi>{{ $paginator->total() }}</bdi> سجل</p>
        <div class="flex flex-wrap gap-2">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true"
                class="rounded-lg border border-stone-200 px-3 py-2 text-stone-400">السابق</span>@else<a
                    rel="prev" href="{{ $paginator->previousPageUrl() }}"
                    class="rounded-lg border border-stone-200 bg-white px-3 py-2">السابق</a>
            @endif
            <span dir="ltr" class="rounded-lg bg-emerald-900 px-3 py-2 text-white" aria-current="page">
                {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
            @if ($paginator->hasMorePages() && $paginator->currentPage() < 1000)
                <a rel="next" href="{{ $paginator->nextPageUrl() }}"
                class="rounded-lg border border-stone-200 bg-white px-3 py-2">التالي</a>@else<span
                    aria-disabled="true"
                    class="rounded-lg border border-stone-200 px-3 py-2 text-stone-400">التالي</span>
            @endif
        </div>
    </nav>
@endif
