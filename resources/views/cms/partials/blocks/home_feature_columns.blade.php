@php
    $surface = trim((string) ($payload['surface'] ?? 'panel'));
    $eyebrow = trim((string) ($payload['eyebrow'] ?? ''));
    $title = trim((string) ($payload['title'] ?? ''));
    $body = trim((string) ($payload['body'] ?? ''));
    $leftItems = is_array($payload['left_items'] ?? null) ? $payload['left_items'] : [];
    $rightItems = is_array($payload['right_items'] ?? null) ? $payload['right_items'] : [];
    $ctaLabel = trim((string) ($payload['cta_label'] ?? ''));
    $ctaUrl = trim((string) ($payload['cta_url'] ?? ''));
    $footerNote = trim((string) ($payload['footer_note'] ?? ''));
    $sectionClass = $surface === 'panel_strong' ? 'ui-surface-panel-strong py-12 sm:py-16' : 'ui-surface-panel py-12 sm:py-16';
    $itemTextClass = $surface === 'panel_strong'
        ? 'ui-shell-panel space-y-4 rounded-3xl p-7 text-base leading-7 text-[#2d2723] sm:text-lg'
        : 'ui-shell-panel space-y-4 rounded-3xl p-7 text-base leading-7 text-[color:var(--color-site-ink)] sm:text-lg';
    $bodyClass = $surface === 'panel_strong'
        ? 'ui-page-subtitle mt-5 sm:text-xl'
        : 'mt-5 text-lg leading-8 text-[color:var(--color-site-muted)] sm:text-xl';
    $iconColorClass = $surface === 'panel_strong' ? 'text-[color:var(--color-site-brand-text)]' : 'text-[#a6461f]';
@endphp

<section class="{{ $sectionClass }}" data-home-reveal>
    <div class="mx-auto max-w-[1320px] px-6 md:px-10">
        <div class="mx-auto max-w-[960px] text-center">
            @if($eyebrow !== '')
                <p class="ui-eyebrow justify-center" data-home-reveal>{{ $eyebrow }}</p>
            @endif
            @if($title !== '')
                <h2 class="mt-3 text-4xl font-extrabold tracking-[-0.05em] text-[color:var(--color-site-ink)] sm:text-5xl" data-home-reveal data-home-delay="80">{{ $title }}</h2>
            @endif
            @if($body !== '')
                <div class="{{ $bodyClass }} cms-markdown" data-home-reveal data-home-delay="140">
                    @cmsMarkdown($body)
                </div>
            @endif
        </div>

        <div class="mt-12 grid gap-6 md:grid-cols-2">
            <ul class="{{ $itemTextClass }}" data-home-reveal data-home-delay="120">
                @foreach($leftItems as $item)
                    @php
                        $label = trim((string) (is_array($item) ? ($item['label'] ?? '') : ''));
                        $itemBody = trim((string) (is_array($item) ? ($item['body'] ?? '') : ''));
                    @endphp
                    @if($label !== '' || $itemBody !== '')
                        <li><i class="fa-solid fa-circle-check mr-2 {{ $iconColorClass }}"></i> <strong>{{ $label }}:</strong> @cmsMarkdownInline($itemBody)</li>
                    @endif
                @endforeach
            </ul>

            <ul class="{{ $itemTextClass }}" data-home-reveal data-home-delay="200">
                @foreach($rightItems as $item)
                    @php
                        $label = trim((string) (is_array($item) ? ($item['label'] ?? '') : ''));
                        $itemBody = trim((string) (is_array($item) ? ($item['body'] ?? '') : ''));
                    @endphp
                    @if($label !== '' || $itemBody !== '')
                        <li><i class="fa-solid fa-circle-check mr-2 {{ $iconColorClass }}"></i> <strong>{{ $label }}:</strong> @cmsMarkdownInline($itemBody)</li>
                    @endif
                @endforeach
            </ul>
        </div>

        @if($ctaLabel !== '' && $ctaUrl !== '')
            <div class="mt-10 text-center" data-home-reveal data-home-delay="260">
                <a href="{{ $ctaUrl }}" class="ui-btn-primary !px-7 !py-3.5">
                    {{ $ctaLabel }}
                    <i class="fa-solid fa-arrow-right-long" aria-hidden="true"></i>
                </a>
            </div>
        @endif

        @if($footerNote !== '')
            <p class="ui-small-meta mt-8 text-center italic" data-home-reveal data-home-delay="260">{{ $footerNote }}</p>
        @endif
    </div>
</section>
