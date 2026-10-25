@php
    $title = trim((string) ($payload['title'] ?? ''));
    $body = trim((string) ($payload['body'] ?? ''));
    $theme = trim((string) ($payload['theme'] ?? 'dark'));
    $primaryLabel = trim((string) ($payload['primary_label'] ?? ''));
    $primaryUrl = trim((string) ($payload['primary_url'] ?? ''));
    $secondaryLabel = trim((string) ($payload['secondary_label'] ?? ''));
    $secondaryUrl = trim((string) ($payload['secondary_url'] ?? ''));
    $sectionClasses = match ($theme) {
        'warm' => 'bg-[color:var(--color-site-panel)] py-12 text-center sm:py-16',
        default => 'bg-[#212529] py-12 text-center text-white sm:py-16',
    };
    $headingClass = $theme === 'warm'
        ? 'mb-4 text-4xl font-black text-[color:var(--color-site-ink)]'
        : 'mb-4 text-4xl font-bold text-white';
    $bodyClass = $theme === 'warm'
        ? 'mb-5 text-[color:var(--color-site-muted)]'
        : 'mb-5 text-white/85';
@endphp

<section class="{{ $sectionClasses }}">
    <div class="mx-auto w-full max-w-[1140px] px-4">
        <div class="flex justify-center">
            <div class="w-full motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none lg:w-8/12">
                @if($title !== '')
                    <h2 class="{{ $headingClass }}">{{ $title }}</h2>
                @endif
                @if($body !== '')
                    <div class="{{ $bodyClass }} cms-markdown">@cmsMarkdown($body)</div>
                @endif
                @if(($primaryLabel !== '' && $primaryUrl !== '') || ($secondaryLabel !== '' && $secondaryUrl !== ''))
                    <div class="flex flex-wrap justify-center gap-3">
                        @if($primaryLabel !== '' && $primaryUrl !== '')
                            <a href="{{ $primaryUrl }}" class="ui-btn-primary">{{ $primaryLabel }}</a>
                        @endif
                        @if($secondaryLabel !== '' && $secondaryUrl !== '')
                            <a href="{{ $secondaryUrl }}" class="ui-btn-secondary ui-btn-secondary-on-dark">{{ $secondaryLabel }}</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
