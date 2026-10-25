@php
    $title = trim((string) ($payload['title'] ?? ''));
    $leftItems = is_array($payload['left_items'] ?? null) ? $payload['left_items'] : [];
    $rightItems = is_array($payload['right_items'] ?? null) ? $payload['right_items'] : [];
@endphp

<section class="ui-surface-panel-strong py-12 sm:py-16" data-home-reveal>
    <div class="mx-auto max-w-[1320px] px-6 md:px-10">
        @if($title !== '')
            <h2 class="text-center text-4xl font-extrabold tracking-[-0.05em] text-[color:var(--color-site-ink)] sm:text-5xl" data-home-reveal>{{ $title }}</h2>
        @endif

        <div class="mt-12 grid gap-6 xl:grid-cols-[1fr_1.05fr_1fr] xl:items-center">
            <div class="space-y-5">
                @foreach($leftItems as $index => $item)
                    @php
                        $itemTitle = trim((string) (is_array($item) ? ($item['title'] ?? '') : ''));
                        $body = trim((string) (is_array($item) ? ($item['body'] ?? '') : ''));
                        $delay = 40 + ($index * 80);
                    @endphp
                    @if($itemTitle !== '')
                        <article class="ui-shell-panel rounded-3xl p-6" data-home-reveal data-home-delay="{{ $delay }}">
                            <h3 class="text-2xl font-bold text-[color:var(--color-site-ink)]">{{ $itemTitle }}</h3>
                            @if($body !== '')
                                <div class="mt-3 text-base leading-7 text-[color:var(--color-site-muted)] cms-markdown">@cmsMarkdown($body)</div>
                            @endif
                        </article>
                    @endif
                @endforeach
            </div>

            <div data-home-reveal data-home-delay="120">
                @if ($productBoxImageUrl)
                    <img
                        src="{{ $productBoxImageUrl }}"
                        alt="{{ $productBoxImageAlt ?: 'included' }}"
                        class="ui-shell-panel h-[320px] w-full rounded-3xl object-cover sm:h-[420px] xl:h-[520px]"
                        data-home-parallax
                        data-home-parallax-speed="0.07"
                        data-home-glow
                    >
                @else
                    <div class="ui-shell-panel h-[320px] w-full rounded-3xl bg-[#ebe3d4] sm:h-[420px] xl:h-[520px]" data-home-parallax data-home-parallax-speed="0.07" data-home-glow></div>
                @endif
            </div>

            <div class="space-y-5">
                @foreach($rightItems as $index => $item)
                    @php
                        $itemTitle = trim((string) (is_array($item) ? ($item['title'] ?? '') : ''));
                        $body = trim((string) (is_array($item) ? ($item['body'] ?? '') : ''));
                        $delay = 80 + ($index * 80);
                    @endphp
                    @if($itemTitle !== '')
                        <article class="ui-shell-panel rounded-3xl p-6" data-home-reveal data-home-delay="{{ $delay }}">
                            <h3 class="text-2xl font-bold text-[color:var(--color-site-ink)]">{{ $itemTitle }}</h3>
                            @if($body !== '')
                                <div class="mt-3 text-base leading-7 text-[color:var(--color-site-muted)] cms-markdown">@cmsMarkdown($body)</div>
                            @endif
                        </article>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</section>
