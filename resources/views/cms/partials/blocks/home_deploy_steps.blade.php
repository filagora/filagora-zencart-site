@php
    $eyebrow = trim((string) ($payload['eyebrow'] ?? ''));
    $title = trim((string) ($payload['title'] ?? ''));
    $steps = is_array($payload['steps'] ?? null) ? $payload['steps'] : [];
    $firstStep = $steps[0] ?? null;
    $firstImageKey = is_array($firstStep) ? trim((string) ($firstStep['image_key'] ?? '')) : '';
@endphp

<section class="ui-surface-base py-12 sm:py-16" data-home-reveal>
    <div class="mx-auto grid max-w-[1320px] gap-8 px-6 md:px-10 xl:grid-cols-[0.92fr_1.35fr] xl:items-center">
        <div>
            @if($eyebrow !== '')
                <p class="ui-eyebrow" data-home-reveal>{{ $eyebrow }}</p>
            @endif
            @if($title !== '')
                <h2 class="mt-3 text-4xl font-extrabold leading-tight tracking-[-0.05em] text-[color:var(--color-site-ink)] sm:text-5xl" data-home-reveal data-home-delay="80">{{ $title }}</h2>
            @endif

            <div class="mt-8 space-y-3">
                @foreach($steps as $index => $step)
                    @php
                        $stepTitle = trim((string) (is_array($step) ? ($step['title'] ?? '') : ''));
                        $body = trim((string) (is_array($step) ? ($step['body'] ?? '') : ''));
                        $imageKey = trim((string) (is_array($step) ? ($step['image_key'] ?? '') : ''));
                        $imagePosition = trim((string) (is_array($step) ? ($step['image_position'] ?? 'center center') : 'center center'));
                        $delay = 120 + ($index * 60);
                        $isOpen = (bool) (is_array($step) ? ($step['open'] ?? false) : false);
                    @endphp
                    @if($stepTitle !== '')
                        <details
                            class="ui-shell-panel group rounded-2xl p-4"
                            data-step-image="{{ $imageKey !== '' ? ($deployStepImageUrls[$imageKey] ?? '') : '' }}"
                            data-step-position="{{ $imagePosition !== '' ? $imagePosition : 'center center' }}"
                            data-home-reveal
                            data-home-delay="{{ $delay }}"
                            @if($isOpen) open @endif
                        >
                            <summary class="cursor-pointer list-none text-lg font-bold text-[color:var(--color-site-ink)] sm:text-xl">
                                {{ $stepTitle }}
                            </summary>
                            @if($body !== '')
                                <div class="mt-3 text-sm leading-6 text-[color:var(--color-site-muted)] sm:text-base cms-markdown">@cmsMarkdown($body)</div>
                            @endif
                        </details>
                    @endif
                @endforeach
            </div>
        </div>

        <div data-home-reveal data-home-delay="140">
            <img
                id="deploySteps"
                src="{{ $firstImageKey !== '' ? ($deployStepImageUrls[$firstImageKey] ?? '') : '' }}"
                alt="Deployment steps"
                class="ui-shell-panel h-[320px] w-full rounded-3xl object-cover sm:h-[420px] xl:h-[520px]"
                data-home-parallax
                data-home-parallax-speed="0.08"
                data-home-glow
            >
        </div>
    </div>
</section>
