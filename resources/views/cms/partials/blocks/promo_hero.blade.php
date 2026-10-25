@php
    $benefits = is_array($payload['benefits'] ?? null) ? $payload['benefits'] : [];
    $ctaLabel = trim((string) ($payload['cta_label'] ?? ''));
    $ctaUrl = trim((string) ($payload['cta_url'] ?? ''));
    $versionBadge = trim((string) ($payload['badge'] ?? ''));
    $checksumLabel = trim((string) ($payload['meta_label'] ?? 'SHA256'));
    $checksumValue = trim((string) ($payload['meta_value'] ?? ''));
    $checksumHelpLabel = trim((string) ($payload['meta_help_label'] ?? 'verify file integrity'));
    $checksumHelpUrl = trim((string) ($payload['meta_help_url'] ?? ''));
    $secondaryLinkLabel = trim((string) ($payload['secondary_link_label'] ?? ''));
    $secondaryLinkUrl = trim((string) ($payload['secondary_link_url'] ?? ''));
@endphp

<section class="overflow-hidden">
    <div>
        <div class="mx-auto w-full max-w-[1140px] px-4">
            <div class="py-12 sm:py-16">
                <div class="grid grid-cols-1 gap-y-10 lg:grid-cols-12 lg:items-center">
                    <div class="lg:col-span-8">
                        <div>
                            <div class="ui-section-title motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none">
                                <h2 class="text-4xl font-bold text-[#0f2133]">{{ $payload['title'] ?? '' }}</h2>
                                @if(!empty($payload['body']))
                                    <div class="ui-section-desc cms-markdown">@cmsMarkdown($payload['body'])</div>
                                @endif
                            </div>

                            @if($benefits !== [])
                                <div class="ui-checklist-wrap motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none">
                                    <ul class="ui-checklist">
                                        @foreach($benefits as $item)
                                            @php
                                                $text = is_array($item) ? (string) ($item['text'] ?? '') : (string) $item;
                                            @endphp
                                            @if(trim($text) !== '')
                                                <li>
                                                    <i class="fa-solid fa-circle-check"></i>
                                                    {{ $text }}
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if($ctaLabel !== '' && $ctaUrl !== '')
                                <a class="ui-btn-primary motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none" href="{{ $ctaUrl }}">
                                    {{ $ctaLabel }}
                                    @if($versionBadge !== '')
                                        <span class="version-badge">{{ $versionBadge }}</span>
                                    @endif
                                    <i class="fa-solid fa-arrow-right-long"></i>
                                </a>
                            @endif

                            @if($checksumValue !== '')
                                <p class="mt-3">
                                    {{ $checksumLabel }}: {{ $checksumValue }}<br>
                                    @if($checksumHelpUrl !== '')
                                        Tip: Use the SHA checksum shown to <a class="font-semibold text-[#8f3f1f] hover:underline focus-visible:underline" href="{{ $checksumHelpUrl }}" target="_blank" rel="noopener noreferrer">{{ $checksumHelpLabel }}</a>
                                    @endif
                                </p>
                            @endif

                            @if($secondaryLinkLabel !== '' && $secondaryLinkUrl !== '')
                                <p class="mt-5"><a class="font-semibold text-[#8f3f1f] hover:underline focus-visible:underline" href="{{ $secondaryLinkUrl }}" target="_blank" rel="noopener noreferrer">{{ $secondaryLinkLabel }}</a></p>
                            @endif
                        </div>
                    </div>
                    <div class="motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none lg:col-span-4">
                        <div>
                            @if ($productBoxImageUrl)
                                <img src="{{ $productBoxImageUrl }}" alt="{{ $productBoxImageAlt ?: 'Download Zen Cart' }}">
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
