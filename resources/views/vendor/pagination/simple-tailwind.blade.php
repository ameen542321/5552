@if ($paginator->hasPages())
    <nav role="navigation" aria-label="التنقل بين صفحات النتائج" class="flex flex-wrap items-center justify-between gap-3">
        @if ($paginator->onFirstPage())
            <span class="ui-btn ui-btn-secondary cursor-not-allowed opacity-60" aria-disabled="true">@lang('pagination.previous')</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="ui-btn ui-btn-secondary">@lang('pagination.previous')</a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="ui-btn ui-btn-secondary">@lang('pagination.next')</a>
        @else
            <span class="ui-btn ui-btn-secondary cursor-not-allowed opacity-60" aria-disabled="true">@lang('pagination.next')</span>
        @endif
    </nav>
@endif
