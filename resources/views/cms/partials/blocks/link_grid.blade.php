@php
    $title = trim((string) ($payload['title'] ?? ''));
    $body = trim((string) ($payload['body'] ?? ''));
    $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
@endphp

<section class="bg-[#fffdf5] py-12 sm:py-16">
    <div class="cms-page-shell !max-w-[1140px] !py-0">
        @if($title !== '' || $body !== '')
            <div class="mx-auto mb-10 max-w-3xl text-center">
                @if($title !== '')
                    <h2 class="text-4xl font-black text-[#0f2133]">{{ $title }}</h2>
                @endif
                @if($body !== '')
                    <div class="mt-4 text-[1rem] leading-7 text-[#4b5968] cms-markdown">@cmsMarkdown($body)</div>
                @endif
            </div>
        @endif

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach($items as $item)
                @php
                    $itemTitle = trim((string) (is_array($item) ? ($item['title'] ?? '') : ''));
                    $itemBody = trim((string) (is_array($item) ? ($item['body'] ?? '') : ''));
                    $itemUrl = trim((string) (is_array($item) ? ($item['url'] ?? '') : ''));
                @endphp
                @if($itemTitle !== '')
                    @if($itemUrl !== '')
                        <a href="{{ $itemUrl }}" class="group rounded-2xl border border-[#e6dccb] bg-white p-6 shadow-[0_14px_28px_rgba(15,33,51,0.06)] transition hover:border-[#933c1f]/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#933c1f] focus-visible:ring-offset-2 focus-visible:ring-offset-[#fffdf5]">
                    @else
                        <div class="rounded-2xl border border-[#e6dccb] bg-white p-6 shadow-[0_14px_28px_rgba(15,33,51,0.06)]">
                    @endif
                        <h3 class="text-xl font-black text-[#20170f] transition group-hover:text-[#933c1f]">{{ $itemTitle }}</h3>
                        @if($itemBody !== '')
                            <div class="mt-3 text-[0.98rem] leading-7 text-[#5c4a3d] cms-markdown">@cmsMarkdown($itemBody)</div>
                        @endif
                    @if($itemUrl !== '')
                        </a>
                    @else
                        </div>
                    @endif
                @endif
            @endforeach
        </div>
    </div>
</section>
