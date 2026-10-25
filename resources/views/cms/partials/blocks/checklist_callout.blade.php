@php
    $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
    $calloutTitle = trim((string) ($payload['callout_title'] ?? ''));
    $calloutBody = trim((string) ($payload['callout_body'] ?? ''));
    $calloutLinkLabel = trim((string) ($payload['callout_link_label'] ?? ''));
    $calloutLinkUrl = trim((string) ($payload['callout_link_url'] ?? ''));
@endphp

<section class="overflow-hidden bg-gradient-1">
    <div>
        <div class="mx-auto w-full max-w-[1140px] px-4">
            <div class="py-12 sm:py-16">
                <div class="mb-10 flex justify-center text-center">
                    <div class="w-full lg:w-10/12">
                        <div class="ui-section-title motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none">
                            <h2 class="text-4xl font-bold text-[#0f2133]">{{ $payload['title'] ?? '' }}</h2>
                            @if(!empty($payload['subtitle']))
                                <p class="ui-section-desc">{{ $payload['subtitle'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="w-full">
                    @if($items !== [])
                        <div class="ui-checklist-wrap motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none">
                            <ul class="ui-checklist">
                                @foreach($items as $item)
                                    @php
                                        $label = is_array($item) ? trim((string) ($item['label'] ?? '')) : '';
                                        $body = is_array($item) ? trim((string) ($item['body'] ?? '')) : '';
                                    @endphp
                                    @if($label !== '' || $body !== '')
                                        <li>
                                            <i class="fa-solid fa-circle-check"></i>
                                            @if($label !== '')
                                                <strong>{{ $label }}:</strong>
                                            @endif
                                            @cmsMarkdownInline($body)
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($calloutTitle !== '' || $calloutBody !== '')
                        <div class="mt-5 rounded-2xl border border-[#d9cbc2] bg-white p-6">
                            @if($calloutTitle !== '')
                                <h2 class="ui-section-kicker motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none">{{ $calloutTitle }}</h2>
                            @endif
                            <div class="mt-3">
                                <p class="text-[#304458] motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none">
                                    @cmsMarkdownInline($calloutBody)
                                    @if($calloutLinkLabel !== '' && $calloutLinkUrl !== '')
                                        <a class="font-semibold text-[#8f3f1f] hover:underline focus-visible:underline" href="{{ $calloutLinkUrl }}" target="_blank" rel="noopener noreferrer">{{ $calloutLinkLabel }}</a>
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
