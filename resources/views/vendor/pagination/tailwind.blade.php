@if ($paginator->hasPages())
    <nav role="navigation" aria-label="التنقل بين صفحات النتائج" class="mt-6 flex flex-wrap items-center justify-between gap-3">
        @if ($paginator->onFirstPage())
            <span class="ui-btn ui-btn-secondary cursor-not-allowed opacity-60" aria-disabled="true">السابق</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="ui-btn ui-btn-secondary">السابق</a>
        @endif

        <div class="order-last hidden w-full items-center justify-center gap-2 overflow-x-auto sm:flex lg:order-none lg:w-auto">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-3 py-2 ui-text-muted" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="ui-btn ui-btn-primary" aria-current="page" aria-label="الصفحة الحالية، {{ $page }}">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="ui-btn ui-btn-secondary" aria-label="الانتقال إلى الصفحة {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="ui-btn ui-btn-secondary">التالي</a>
        @else
            <span class="ui-btn ui-btn-secondary cursor-not-allowed opacity-60" aria-disabled="true">التالي</span>
        @endif
    </nav>
@endif
