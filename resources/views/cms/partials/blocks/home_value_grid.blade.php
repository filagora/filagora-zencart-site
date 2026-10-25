@php
    $eyebrow = trim((string) ($payload['eyebrow'] ?? ''));
    $title = trim((string) ($payload['title'] ?? ''));
    $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
@endphp

<section class="ui-surface-panel pb-12 sm:pb-16" data-home-reveal>
    <div class="mx-auto max-w-[1320px] px-6 md:px-10">
        <div class="mx-auto max-w-[760px] text-center">
            @if($eyebrow !== '')
                <p class="ui-eyebrow justify-center" data-home-reveal>{{ $eyebrow }}</p>
            @endif
            @if($title !== '')
                <h2 class="mt-3 text-4xl font-extrabold tracking-[-0.05em] text-[color:var(--color-site-ink)] sm:text-5xl" data-home-reveal>{{ $title }}</h2>
            @endif
        </div>

        <div class="mt-12 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach($items as $index => $item)
                @php
                    $iconClass = trim((string) (is_array($item) ? ($item['icon_class'] ?? '') : ''));
                    $itemTitle = trim((string) (is_array($item) ? ($item['title'] ?? '') : ''));
                    $body = trim((string) (is_array($item) ? ($item['body'] ?? '') : ''));
                    $url = trim((string) (is_array($item) ? ($item['url'] ?? '') : ''));
                    $highlight = (bool) (is_array($item) ? ($item['highlight'] ?? false) : false);
                    $delay = 40 + ($index * 60);
                    $classes = $highlight
                        ? 'ui-stat-card rounded-[1.7rem] px-5 py-5 ring-2 ring-[color:var(--color-site-brand)]/15'
                        : 'ui-stat-card rounded-[1.7rem] px-5 py-5';
                @endphp
                @if($itemTitle !== '')
                    @if($url !== '')
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="{{ $classes }}" data-home-reveal data-home-delay="{{ $delay }}">
                    @else
                        <div class="{{ $classes }}" data-home-reveal data-home-delay="{{ $delay }}">
                    @endif
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-[color:var(--color-site-brand-text)] text-xl text-white">
                            <i class="{{ $iconClass !== '' ? $iconClass : 'fa fa-star' }}" aria-hidden="true"></i>
                        </span>
                        <span class="mt-4 block text-[1.65rem] font-bold leading-tight text-[color:var(--color-site-ink)]">{{ $itemTitle }}</span>
                        @if($body !== '')
                            <span class="mt-2 block text-sm leading-6 text-[color:var(--color-site-muted)]">@cmsMarkdownInline($body)</span>
                        @endif
                    @if($url !== '')
                        </a>
                    @else
                        </div>
                    @endif
                @endif
            @endforeach
        </div>
    </div>
</section>
