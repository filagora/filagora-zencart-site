@php
    $eyebrow = trim((string) ($payload['eyebrow'] ?? ''));
    $title = trim((string) ($payload['title'] ?? ''));
    $body = trim((string) ($payload['body'] ?? ''));
    $ownershipTitle = trim((string) ($payload['ownership_title'] ?? ''));
    $ownershipParagraphs = is_array($payload['ownership_paragraphs'] ?? null) ? $payload['ownership_paragraphs'] : [];
    $ownershipCtaLabel = trim((string) ($payload['ownership_cta_label'] ?? ''));
    $ownershipCtaUrl = trim((string) ($payload['ownership_cta_url'] ?? ''));
    $costTitle = trim((string) ($payload['cost_title'] ?? ''));
    $costRows = is_array($payload['cost_rows'] ?? null) ? $payload['cost_rows'] : [];
    $requirementsTitle = trim((string) ($payload['requirements_title'] ?? ''));
    $requirementsRows = is_array($payload['requirements_rows'] ?? null) ? $payload['requirements_rows'] : [];
    $requirementsBodyTitle = trim((string) ($payload['requirements_body_title'] ?? ''));
    $requirementsBodyParagraphs = is_array($payload['requirements_body_paragraphs'] ?? null) ? $payload['requirements_body_paragraphs'] : [];
    $requirementsCtaLabel = trim((string) ($payload['requirements_cta_label'] ?? ''));
    $requirementsCtaUrl = trim((string) ($payload['requirements_cta_url'] ?? ''));
    $requirementsBodyText = implode(' ', array_values(array_filter(array_map(static function (mixed $paragraph): string {
        return trim((string) (is_array($paragraph) ? ($paragraph['text'] ?? '') : $paragraph));
    }, $requirementsBodyParagraphs), static fn (string $paragraph): bool => $paragraph !== '')));
@endphp

<section class="ui-surface-base py-12 sm:py-16" data-home-reveal>
    <div class="mx-auto max-w-[1320px] px-6 md:px-10">
        <div class="mx-auto max-w-[980px] text-center">
            @if($eyebrow !== '')
                <p class="ui-eyebrow justify-center" data-home-reveal>{{ $eyebrow }}</p>
            @endif
            @if($title !== '')
                <h2 class="mt-3 text-4xl font-extrabold tracking-[-0.05em] text-[#051a30] sm:text-5xl" data-home-reveal data-home-delay="80">{{ $title }}</h2>
            @endif
            @if($body !== '')
                <div class="ui-page-subtitle mt-5 sm:text-xl cms-markdown" data-home-reveal data-home-delay="140">
                    @cmsMarkdown($body)
                </div>
            @endif
        </div>

        <div class="mt-12 grid gap-8 lg:grid-cols-[1.35fr_1fr] lg:items-center">
            <div data-home-reveal data-home-delay="120">
                @if($ownershipTitle !== '')
                    <h3 class="ui-content-title text-[color:var(--color-site-ink)]">{{ $ownershipTitle }}</h3>
                @endif
                @foreach($ownershipParagraphs as $paragraph)
                    @php
                        if (is_array($paragraph)) {
                            $text = trim((string) ($paragraph['text'] ?? ''));
                            $emphasizedText = trim((string) ($paragraph['emphasized_text'] ?? ''));
                        } else {
                            $rawText = trim((string) $paragraph);
                            $matches = [];
                            preg_match('/^(.*?)(?:<strong>(.*?)<\/strong>)?\s*$/i', $rawText, $matches);
                            $text = trim(strip_tags((string) ($matches[1] ?? $rawText)));
                            $emphasizedText = trim(strip_tags((string) ($matches[2] ?? '')));
                        }
                    @endphp
                    @if($text !== '' || $emphasizedText !== '')
                        <p class="ui-body-copy mt-4">
                            @cmsMarkdownInline($text)
                            @if($emphasizedText !== '')
                                <strong>{{ $emphasizedText }}</strong>
                            @endif
                        </p>
                    @endif
                @endforeach
                @if($ownershipCtaLabel !== '' && $ownershipCtaUrl !== '')
                    <a href="{{ $ownershipCtaUrl }}" class="ui-btn-primary mt-6 !px-8 !py-3">
                        {{ $ownershipCtaLabel }}
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </a>
                @endif
            </div>

            <div class="ui-shell-panel rounded-3xl p-6" data-home-reveal data-home-delay="200">
                @if($costTitle !== '')
                    <h3 class="ui-content-title text-[color:var(--color-site-brand-dark)]">{{ $costTitle }}</h3>
                @endif
                <ul class="mt-5 space-y-3 text-base text-[#2d2723]">
                    @foreach($costRows as $index => $row)
                        @php
                            $iconClass = trim((string) (is_array($row) ? ($row['icon_class'] ?? '') : ''));
                            $label = trim((string) (is_array($row) ? ($row['label'] ?? '') : ''));
                            $value = trim((string) (is_array($row) ? ($row['value'] ?? '') : ''));
                            $border = $index === array_key_last($costRows) ? '' : ' border-b border-[color:var(--color-site-line)] pb-2';
                        @endphp
                        @if($label !== '' || $value !== '')
                            <li class="flex items-center justify-between{{ $border }}"><span><i class="{{ $iconClass !== '' ? $iconClass : 'fa-solid fa-circle' }} mr-2 text-[color:var(--color-site-brand-text)]"></i> {{ $label }}</span><span>{{ $value }}</span></li>
                        @endif
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="ui-divider-soft my-12 h-px" data-home-reveal data-home-delay="160"></div>

        <div class="grid gap-8 lg:grid-cols-[1fr_1.35fr] lg:items-center">
            <div class="ui-surface-dark order-2 rounded-3xl border border-[#3d342d] p-6 text-white lg:order-1" data-home-reveal data-home-delay="120">
                @if($requirementsTitle !== '')
                    <h3 class="ui-content-title text-white">{{ $requirementsTitle }}</h3>
                @endif
                <ul class="mt-5 space-y-3 text-base">
                    @foreach($requirementsRows as $index => $row)
                        @php
                            $iconClass = trim((string) (is_array($row) ? ($row['icon_class'] ?? '') : ''));
                            $label = trim((string) (is_array($row) ? ($row['label'] ?? '') : ''));
                            $value = trim((string) (is_array($row) ? ($row['value'] ?? '') : ''));
                            $border = $index === array_key_last($requirementsRows) ? '' : ' border-b border-white/20 pb-2';
                        @endphp
                        @if($label !== '' || $value !== '')
                            <li class="flex items-center justify-between{{ $border }}"><span><i class="{{ $iconClass !== '' ? $iconClass : 'fa-solid fa-circle' }} mr-2 text-[color:var(--color-site-gold)]"></i> {{ $label }}</span><span>{{ $value }}</span></li>
                        @endif
                    @endforeach
                </ul>
            </div>

            <div class="order-1 lg:order-2" data-home-reveal data-home-delay="200">
                @if($requirementsBodyTitle !== '')
                    <h3 class="ui-content-title text-[color:var(--color-site-ink)]">{{ $requirementsBodyTitle }}</h3>
                @endif
                @if($requirementsBodyText !== '')
                    <div class="ui-body-copy mt-4 cms-markdown">@cmsMarkdown($requirementsBodyText)</div>
                @endif
                @if($requirementsCtaLabel !== '' && $requirementsCtaUrl !== '')
                    <a href="{{ $requirementsCtaUrl }}" class="ui-btn-primary mt-6 !px-8 !py-3">
                        {{ $requirementsCtaLabel }}
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>
