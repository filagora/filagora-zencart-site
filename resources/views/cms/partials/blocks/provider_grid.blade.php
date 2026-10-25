@php
    $title = trim((string) ($payload['title'] ?? ''));
    $body = trim((string) ($payload['body'] ?? ''));
    $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
    $surface = trim((string) ($payload['surface'] ?? 'panel'));
    $columns = (int) ($payload['columns'] ?? 2);
    $sectionClass = $surface === 'soft'
        ? 'bg-[#fffdf5] py-12 sm:py-16'
        : 'bg-[color:var(--color-site-panel)] py-12 sm:py-16';
    $gridClass = $columns >= 3
        ? 'grid gap-6 md:grid-cols-2 xl:grid-cols-3'
        : 'grid gap-6 md:grid-cols-2';
@endphp

<section class="{{ $sectionClass }}">
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

        <div class="{{ $gridClass }}">
            @foreach($items as $item)
                @php
                    $name = trim((string) (is_array($item) ? ($item['name'] ?? '') : ''));
                    $itemBody = trim((string) (is_array($item) ? ($item['body'] ?? '') : ''));
                    $itemUrl = trim((string) (is_array($item) ? ($item['url'] ?? '') : ''));
                    $imageUrl = trim((string) (is_array($item) ? ($item['image_url'] ?? '') : ''));
                    $logoHtml = trim((string) (is_array($item) ? ($item['logo_html'] ?? '') : ''));
                    $badge = trim((string) (is_array($item) ? ($item['badge'] ?? '') : ''));
                    $ctaLabel = trim((string) (is_array($item) ? ($item['cta_label'] ?? '') : ''));
                    $tags = array_values(array_filter(is_array($item) && is_array($item['tags'] ?? null) ? $item['tags'] : [], static fn (mixed $tag): bool => trim((string) $tag) !== ''));
                    $darkLogoBackground = (bool) (is_array($item) ? ($item['dark_logo_background'] ?? false) : false);
                @endphp
                @if($name !== '')
                    <article class="rounded-2xl border border-[#e6dccb] bg-white p-6 shadow-[0_14px_28px_rgba(15,33,51,0.06)]">
                        @if($logoHtml !== '')
                            <div class="mb-5 flex h-12 items-center {{ $darkLogoBackground ? 'rounded-lg bg-black p-2.5' : '' }}">
                                {!! $logoHtml !!}
                            </div>
                        @elseif($imageUrl !== '')
                            <div class="mb-5 flex h-12 items-center {{ $darkLogoBackground ? 'rounded-lg bg-black p-2.5' : '' }}">
                                <img src="{{ $imageUrl }}" alt="{{ $name }}" class="h-10 max-w-[160px] object-contain">
                            </div>
                        @endif
                        @if($badge !== '')
                            <p class="mb-3 inline-flex rounded-full border border-[#d9cbc2] bg-[#fffdf5] px-3 py-1 text-[0.68rem] font-bold uppercase tracking-[0.14em] text-[#8d5a3b]">{{ $badge }}</p>
                        @endif
                        <h3 class="text-xl font-black text-[#20170f]">{{ $name }}</h3>
                        @if($itemBody !== '')
                            <div class="mt-3 text-[0.98rem] leading-7 text-[#5c4a3d] cms-markdown">@cmsMarkdown($itemBody)</div>
                        @endif
                        @if($tags !== [])
                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach($tags as $tag)
                                    <span class="inline-flex rounded-full border border-[#e6dccb] bg-[#fffdf5] px-3 py-1 text-[0.72rem] font-bold text-[#8d5a3b]">{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif
                        @if($itemUrl !== '')
                            <a href="{{ $itemUrl }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex items-center text-sm font-semibold text-[#933c1f] hover:underline focus-visible:underline">
                                {{ $ctaLabel !== '' ? $ctaLabel : 'Visit '.$name }} <span class="sr-only">(opens in new tab)</span> &rarr;
                            </a>
                        @endif
                    </article>
                @endif
            @endforeach
        </div>
    </div>
</section>
