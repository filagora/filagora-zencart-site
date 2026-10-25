@php
    $currentPage = max(1, (int) $posts->currentPage());
    $lastPage = max(1, (int) $posts->lastPage());
    $window = 2;
    $start = max(1, $currentPage - $window);
    $end = min($lastPage, $currentPage + $window);

    if ($start <= 3) {
        $start = 1;
    }
    if ($end >= $lastPage - 2) {
        $end = $lastPage;
    }
@endphp

@if($posts->hasPages())
    @php
        $isTop = ($position ?? 'bottom') === 'top';
        $navWrapClass = 'mt-4 border border-[#d8dce2] bg-white p-3';
        $navClass = 'flex flex-col gap-3 md:flex-row md:items-center md:justify-between';
        $groupClass = 'inline-flex items-center gap-2';
        $linkClass = 'inline-flex items-center border border-[#d8dce2] px-2.5 py-1.5 text-sm font-medium text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f]';
        $disabledLinkClass = 'inline-flex items-center border border-[#d8dce2] px-2.5 py-1.5 text-sm font-medium text-[#9aa3ad]';
        $pageClass = 'inline-flex min-w-8 items-center justify-center border border-[#d8dce2] px-2 py-1 text-sm font-medium text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f]';
        $currentPageClass = 'inline-flex min-w-8 items-center justify-center border border-[#933c1f] bg-[#f7e8df] px-2 py-1 text-sm font-semibold text-[#933c1f]';
        $ellipsisClass = 'px-1 text-[#5c6570]';
        $metaClass = 'text-sm text-[#5c6570]';
    @endphp
    <nav class="{{ $isTop ? 'mb-4' : 'mt-4' }} {{ $navWrapClass }}" aria-label="Post list pagination">
        <div class="{{ $navClass }}">
            <div class="{{ $groupClass }}">
            @if($posts->onFirstPage())
                <span class="{{ $disabledLinkClass }}" aria-disabled="true">First</span>
                <span class="{{ $disabledLinkClass }}" aria-disabled="true">Previous</span>
            @else
                <a class="{{ $linkClass }}" href="{{ $posts->url(1) }}">First</a>
                <a class="{{ $linkClass }}" href="{{ $posts->previousPageUrl() }}">Previous</a>
            @endif
            </div>

            <div class="{{ $groupClass }}">
                @if($start > 1)
                    <a class="{{ $pageClass }}" href="{{ $posts->url(1) }}">1</a>
                @endif

                @if($start > 2)
                    <span class="{{ $ellipsisClass }}" aria-hidden="true">…</span>
                @endif

                @for($page = $start; $page <= $end; $page++)
                    @if($page === $currentPage)
                        <span class="{{ $currentPageClass }}" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="{{ $pageClass }}" href="{{ $posts->url($page) }}">{{ $page }}</a>
                    @endif
                @endfor

                @if($end < $lastPage - 1)
                    <span class="{{ $ellipsisClass }}" aria-hidden="true">…</span>
                @endif

                @if($end < $lastPage)
                    <a class="{{ $pageClass }}" href="{{ $posts->url($lastPage) }}">{{ $lastPage }}</a>
                @endif
            </div>

            <div class="{{ $groupClass }}">
            @if($posts->hasMorePages())
                <a class="{{ $linkClass }}" href="{{ $posts->nextPageUrl() }}">Next</a>
                <a class="{{ $linkClass }}" href="{{ $posts->url($lastPage) }}">Last</a>
            @else
                <span class="{{ $disabledLinkClass }}" aria-disabled="true">Next</span>
                <span class="{{ $disabledLinkClass }}" aria-disabled="true">Last</span>
            @endif
            </div>
        </div>

        <div class="{{ $metaClass }}">
            Page {{ $currentPage }} of {{ $lastPage }}
        </div>
    </nav>
@endif
