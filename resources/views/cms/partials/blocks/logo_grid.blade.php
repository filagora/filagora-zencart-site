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

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            @foreach($items as $item)
                @php
                    $name = trim((string) (is_array($item) ? ($item['name'] ?? '') : ''));
                    $itemUrl = trim((string) (is_array($item) ? ($item['url'] ?? '') : ''));
                    $imageUrl = trim((string) (is_array($item) ? ($item['image_url'] ?? '') : ''));
                @endphp
                @if($name !== '' && $imageUrl !== '')
                    @if($itemUrl !== '')
                        <a href="{{ $itemUrl }}" target="_blank" rel="noopener noreferrer" class="flex h-[112px] items-center justify-center rounded-2xl border border-[#e6dccb] bg-white px-5 py-6 transition hover:border-[#933c1f]/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#933c1f] focus-visible:ring-offset-2 focus-visible:ring-offset-[#fffdf5]">
                            <span class="sr-only">{{ $name }} (opens in new tab)</span>
                            <img src="{{ $imageUrl }}" alt="" class="max-h-14 w-auto object-contain" loading="lazy" decoding="async">
                        </a>
                    @else
                        <div class="flex h-[112px] items-center justify-center rounded-2xl border border-[#e6dccb] bg-white px-5 py-6">
                            <img src="{{ $imageUrl }}" alt="{{ $name }}" class="max-h-14 w-auto object-contain" loading="lazy" decoding="async">
                        </div>
                    @endif
                @endif
            @endforeach
        </div>
    </div>
</section>
