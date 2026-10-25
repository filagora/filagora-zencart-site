<section class="ui-surface-base py-12 sm:py-16" data-home-reveal>
    <div class="mx-auto grid max-w-[1320px] gap-8 px-6 md:px-10 xl:grid-cols-[0.92fr_1.35fr] xl:items-center">
        <div>
            <p class="ui-eyebrow" data-home-reveal>How It Works</p>
            <h2 class="mt-3 text-4xl font-extrabold leading-tight tracking-[-0.05em] text-[color:var(--color-site-ink)] sm:text-5xl" data-home-reveal data-home-delay="80">Deploy your website in as little as 10 minutes</h2>

            <div class="mt-8 space-y-3">
                <details
                    class="ui-shell-panel group rounded-2xl p-4"
                    data-step-image="{{ $deployStepImageUrls['cms-media/steps/step_1.jpg'] ?? '' }}"
                    data-step-position="center 35%"
                    data-home-reveal
                    data-home-delay="120"
                    open
                >
                    <summary class="cursor-pointer list-none text-lg font-bold text-[color:var(--color-site-ink)] sm:text-xl">
                        01. Download Zen Cart
                    </summary>
                    <p class="mt-3 text-sm leading-6 text-[color:var(--color-site-muted)] sm:text-base">Download the zip file from our official repository</p>
                </details>

                <details
                    class="ui-shell-panel group rounded-2xl p-4"
                    data-step-image="{{ $deployStepImageUrls['cms-media/steps/step_2.jpg'] ?? '' }}"
                    data-step-position="center center"
                    data-home-reveal
                    data-home-delay="180"
                >
                    <summary class="cursor-pointer list-none text-lg font-bold text-[color:var(--color-site-ink)] sm:text-xl">
                        02. Upload to your server
                    </summary>
                    <p class="mt-3 text-sm leading-6 text-[color:var(--color-site-muted)] sm:text-base">Upload the file to your server</p>
                </details>

                <details
                    class="ui-shell-panel group rounded-2xl p-4"
                    data-step-image="{{ $deployStepImageUrls['cms-media/steps/step_3.jpg'] ?? '' }}"
                    data-step-position="center 30%"
                    data-home-reveal
                    data-home-delay="240"
                >
                    <summary class="cursor-pointer list-none text-lg font-bold text-[color:var(--color-site-ink)] sm:text-xl">
                        03. Install on your server
                    </summary>
                    <p class="mt-3 text-sm leading-6 text-[color:var(--color-site-muted)] sm:text-base">Follow the simple process to install the application on your server</p>
                </details>

                <details
                    class="ui-shell-panel group rounded-2xl p-4"
                    data-step-image="{{ $deployStepImageUrls['cms-media/steps/step_4.jpg'] ?? '' }}"
                    data-step-position="center 25%"
                    data-home-reveal
                    data-home-delay="300"
                >
                    <summary class="cursor-pointer list-none text-lg font-bold text-[color:var(--color-site-ink)] sm:text-xl">
                        04. Ready to go
                    </summary>
                    <p class="mt-3 text-sm leading-6 text-[color:var(--color-site-muted)] sm:text-base">Your site is ready for use. You can always add more plugins to extend functionality</p>
                </details>
            </div>
        </div>

        <div data-home-reveal data-home-delay="140">
            <img
                id="deploySteps"
                src="{{ $deployStepImageUrls['cms-media/steps/step_1.jpg'] ?? '' }}"
                alt="Deployment steps"
                class="ui-shell-panel h-[320px] w-full rounded-3xl object-cover sm:h-[420px] xl:h-[520px]"
                data-home-parallax
                data-home-parallax-speed="0.08"
                data-home-glow
            >
        </div>
    </div>
</section>
