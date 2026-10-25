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
                    <h2 class="text-4xl font-black tracking-[-0.04em] text-[#0f2133]">{{ $title }}</h2>
                @endif
                @if($body !== '')
                    <div class="mt-4 text-[1rem] leading-7 text-[#4b5968] cms-markdown">@cmsMarkdown($body)</div>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            @foreach($items as $item)
                @php
                    $itemTitle = trim((string) (is_array($item) ? ($item['title'] ?? '') : ''));
                    $itemBody = trim((string) (is_array($item) ? ($item['body'] ?? '') : ''));
                    $iconClass = trim((string) (is_array($item) ? ($item['icon_class'] ?? 'fa-solid fa-star') : 'fa-solid fa-star'));
                @endphp
                @if($itemTitle !== '')
                    <article class="rounded-[24px] border border-[#e6dccb] bg-white p-6 shadow-[0_14px_28px_rgba(15,33,51,0.06)]">
                        <div class="mb-4 flex items-center gap-4">
                            <span class="inline-flex h-[70px] w-[70px] items-center justify-center rounded-[18px] bg-[#933c1f] text-white">
                                <i class="{{ $iconClass }} text-xl" aria-hidden="true"></i>
                            </span>
                            <h3 class="text-2xl font-black tracking-[-0.03em] text-[#20170f]">{{ $itemTitle }}</h3>
                        </div>
                        @if($itemBody !== '')
                            <div class="text-[0.98rem] leading-7 text-[#5c4a3d] cms-markdown">@cmsMarkdown($itemBody)</div>
                        @endif
                    </article>
                @endif
            @endforeach
        </div>
    </div>
</section>
