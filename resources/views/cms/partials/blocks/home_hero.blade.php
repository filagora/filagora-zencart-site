@php
    $eyebrow = trim((string) ($payload['eyebrow'] ?? ''));
    $title = trim((string) ($payload['title'] ?? ''));
    $body = trim((string) ($payload['body'] ?? ''));
    $stats = is_array($payload['stats'] ?? null) ? $payload['stats'] : [];
    $primaryLabel = trim((string) ($payload['primary_label'] ?? ''));
    $primaryUrl = trim((string) ($payload['primary_url'] ?? ''));
    $secondaryLabel = trim((string) ($payload['secondary_label'] ?? ''));
    $secondaryUrl = trim((string) ($payload['secondary_url'] ?? ''));
@endphp

<section class="overflow-hidden bg-[color:var(--color-site-bg)]" data-home-reveal>
    <div class="mx-auto grid max-w-[1600px] gap-10 px-6 py-12 md:px-10 lg:grid-cols-[minmax(0,1fr)_minmax(320px,1.02fr)] lg:items-center lg:gap-14 lg:py-16">
        <div>
            @if($eyebrow !== '')
                <p class="ui-eyebrow" data-home-hero-reveal data-home-delay="20">{{ $eyebrow }}</p>
            @endif
            @if($title !== '')
                <h1 class="ui-fluid-title mt-4 max-w-[10ch]" data-home-hero-reveal data-home-delay="60">
                    {{ $title }}
                </h1>
            @endif
            @if($body !== '')
                <div class="ui-fluid-subtitle mt-6 max-w-[62ch] cms-markdown" data-home-hero-reveal data-home-delay="180">
                    @cmsMarkdown($body)
                </div>
            @endif

            @if($stats !== [])
                <div class="mt-8 grid gap-3 sm:grid-cols-3" data-home-hero-reveal data-home-delay="240">
                    @foreach($stats as $stat)
                        @php
                            $label = trim((string) (is_array($stat) ? ($stat['label'] ?? '') : ''));
                            $value = trim((string) (is_array($stat) ? ($stat['value'] ?? '') : ''));
                        @endphp
                        @if($label !== '' || $value !== '')
                            <div class="ui-stat-card rounded-2xl px-4 py-4">
                                <div class="text-xs font-bold uppercase tracking-[0.16em] text-[color:var(--color-site-muted)]">{{ $label }}</div>
                                <div class="mt-2 text-2xl font-extrabold text-[color:var(--color-site-ink)]">{{ $value }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            @if(($primaryLabel !== '' && $primaryUrl !== '') || ($secondaryLabel !== '' && $secondaryUrl !== ''))
                <div class="mt-8 flex flex-wrap gap-3" data-home-hero-reveal data-home-delay="300">
                    @if($primaryLabel !== '' && $primaryUrl !== '')
                        <a href="{{ $primaryUrl }}" class="ui-btn-primary !px-7 !py-3.5">
                            {{ $primaryLabel }}
                            <span aria-hidden="true">→</span>
                        </a>
                    @endif
                    @if($secondaryLabel !== '' && $secondaryUrl !== '')
                        <a href="{{ $secondaryUrl }}" class="ui-btn-secondary !px-7 !py-3.5">
                            {{ $secondaryLabel }}
                            <span aria-hidden="true">→</span>
                        </a>
                    @endif
                </div>
            @endif
        </div>

        <div class="lg:pl-4" data-home-hero-reveal data-home-delay="140">
            @if ($homeIntroImageUrl)
                <img
                    src="{{ $homeIntroImageUrl }}"
                    alt="{{ $homeIntroImageAlt ?: 'online shopping platform' }}"
                    @if($homeIntroImageWidth && $homeIntroImageHeight)
                        width="{{ $homeIntroImageWidth }}" height="{{ $homeIntroImageHeight }}"
                    @endif
                    fetchpriority="high"
                    class="ui-shell-panel ui-mesh-bg h-[340px] w-full rounded-[2rem] object-cover sm:h-[420px] lg:h-[560px]"
                    data-home-parallax
                    data-home-parallax-speed="0.1"
                    data-home-glow
                >
            @else
                <div class="ui-shell-panel ui-mesh-bg h-[340px] w-full rounded-[2rem] sm:h-[420px] lg:h-[560px]" data-home-parallax data-home-parallax-speed="0.1" data-home-glow></div>
            @endif
        </div>
    </div>
</section>
