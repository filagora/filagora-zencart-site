<section class="ui-surface-panel-strong py-12 sm:py-16" data-home-reveal>
    <div class="mx-auto max-w-[1320px] px-6 md:px-10">
        <h2 class="text-center text-4xl font-extrabold tracking-[-0.05em] text-[color:var(--color-site-ink)] sm:text-5xl" data-home-reveal>What's Included</h2>

        <div class="mt-12 grid gap-6 xl:grid-cols-[1fr_1.05fr_1fr] xl:items-center">
            <div class="space-y-5">
                <article class="ui-shell-panel rounded-3xl p-6" data-home-reveal data-home-delay="40">
                    <h3 class="text-2xl font-bold text-[color:var(--color-site-ink)]">It's free</h3>
                    <p class="mt-3 text-base leading-7 text-[color:var(--color-site-muted)]">No monthly costs, no hidden fees, and no artificial ceiling on growth.</p>
                </article>
                <article class="ui-shell-panel rounded-3xl p-6" data-home-reveal data-home-delay="120">
                    <h3 class="text-2xl font-bold text-[color:var(--color-site-ink)]">PCI DSS compliant</h3>
                    <p class="mt-3 text-base leading-7 text-[color:var(--color-site-muted)]">Designed around established payment and security expectations.</p>
                </article>
                <article class="ui-shell-panel rounded-3xl p-6" data-home-reveal data-home-delay="200">
                    <h3 class="text-2xl font-bold text-[color:var(--color-site-ink)]">Deep support history</h3>
                    <p class="mt-3 text-base leading-7 text-[color:var(--color-site-muted)]">Years of discussion and troubleshooting are already part of the platform ecosystem.</p>
                </article>
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
                <article class="ui-shell-panel rounded-3xl p-6" data-home-reveal data-home-delay="80">
                    <h3 class="text-2xl font-bold text-[color:var(--color-site-ink)]">No restrictions</h3>
                    <p class="mt-3 text-base leading-7 text-[color:var(--color-site-muted)]">Unlimited categories, products, transaction volume, and admin users.</p>
                </article>
                <article class="ui-shell-panel rounded-3xl p-6" data-home-reveal data-home-delay="160">
                    <h3 class="text-2xl font-bold text-[color:var(--color-site-ink)]">SEO optimized</h3>
                    <p class="mt-3 text-base leading-7 text-[color:var(--color-site-muted)]">Excellent search-friendly foundations right out of the box.</p>
                </article>
                <article class="ui-shell-panel rounded-3xl p-6" data-home-reveal data-home-delay="240">
                    <h3 class="text-2xl font-bold text-[color:var(--color-site-ink)]">Constant development</h3>
                    <p class="mt-3 text-base leading-7 text-[color:var(--color-site-muted)]">Ongoing releases and public code visibility through Github.</p>
                </article>
            </div>
        </div>
    </div>
</section>
