<section class="overflow-hidden bg-[color:var(--color-site-bg)]" data-home-reveal>
    <div class="mx-auto grid max-w-[1600px] gap-10 px-6 py-12 md:px-10 lg:grid-cols-[minmax(0,1fr)_minmax(320px,1.02fr)] lg:items-center lg:gap-14 lg:py-16">
        <div>
            <p class="ui-eyebrow" data-home-hero-reveal data-home-delay="20">Open Source. Merchant Owned. Community Powered.</p>
            <h1 class="ui-fluid-title mt-4 max-w-[10ch]" data-home-hero-reveal data-home-delay="60">
                Commerce software with room to grow.
            </h1>
            <p class="ui-fluid-subtitle mt-6 max-w-[62ch]" data-home-hero-reveal data-home-delay="180">
                Build a store you control, extend it with plugins, and lean on a long-running support community without getting trapped in platform fees.
            </p>

            <div class="mt-8 grid gap-3 sm:grid-cols-3" data-home-hero-reveal data-home-delay="240">
                <div class="ui-stat-card rounded-2xl px-4 py-4">
                    <div class="text-xs font-bold uppercase tracking-[0.16em] text-[color:var(--color-site-muted)]">Track Record</div>
                    <div class="mt-2 text-2xl font-extrabold text-[color:var(--color-site-ink)]">20+ years</div>
                </div>
                <div class="ui-stat-card rounded-2xl px-4 py-4">
                    <div class="text-xs font-bold uppercase tracking-[0.16em] text-[color:var(--color-site-muted)]">Extensions</div>
                    <div class="mt-2 text-2xl font-extrabold text-[color:var(--color-site-ink)]">2,000+ plugins</div>
                </div>
                <div class="ui-stat-card rounded-2xl px-4 py-4">
                    <div class="text-xs font-bold uppercase tracking-[0.16em] text-[color:var(--color-site-muted)]">Ownership</div>
                    <div class="mt-2 text-2xl font-extrabold text-[color:var(--color-site-ink)]">No platform tax</div>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-3" data-home-hero-reveal data-home-delay="300">
                <a href="{{ route('download') }}" class="ui-btn-primary !px-7 !py-3.5">
                    Get Started Now
                    <span aria-hidden="true">→</span>
                </a>
                <a href="{{ route('about') }}" class="ui-btn-secondary !px-7 !py-3.5">
                    Learn More
                    <span aria-hidden="true">→</span>
                </a>
            </div>
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
