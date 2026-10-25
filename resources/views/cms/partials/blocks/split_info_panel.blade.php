@php
    $paragraphs = is_array($payload['paragraphs'] ?? null) ? $payload['paragraphs'] : [];
    $specs = is_array($payload['specs'] ?? null) ? $payload['specs'] : [];
    $ctaLabel = trim((string) ($payload['cta_label'] ?? ''));
    $ctaUrl = trim((string) ($payload['cta_url'] ?? ''));
@endphp

<section class="bg-[#fffff0] py-12 sm:py-16">
    <div>
        <div class="mx-auto w-full max-w-[1140px] px-4">
            <div class="mb-10 flex justify-center text-center">
                <div class="w-full lg:w-8/12">
                    <div class="ui-section-title motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none">
                        <h2 class="text-4xl font-bold text-[#0f2133]">{{ $payload['title'] ?? '' }}</h2>
                        @if(!empty($payload['subtitle']))
                            <p class="ui-section-desc">{{ $payload['subtitle'] }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-y-8 lg:grid-cols-12 lg:items-center">
                <div class="motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none lg:col-span-7 lg:pr-12">
                    @if(!empty($payload['body_title']))
                        <h3 class="mb-3 text-2xl font-bold">{{ $payload['body_title'] }}</h3>
                    @endif
                    @foreach($paragraphs as $paragraph)
                        @php
                            $text = is_array($paragraph) ? (string) ($paragraph['text'] ?? '') : (string) $paragraph;
                        @endphp
                        @if(trim($text) !== '')
                            <div class="mb-3 cms-markdown">@cmsMarkdown($text)</div>
                        @endif
                    @endforeach
                    @if($ctaLabel !== '' && $ctaUrl !== '')
                        <a href="{{ $ctaUrl }}" target="_blank" rel="noopener noreferrer" class="ui-btn-secondary">
                            {{ $ctaLabel }}
                            <i class="fa-solid fa-arrow-right-long ml-2"></i>
                        </a>
                    @endif
                </div>

                <div class="motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none lg:col-span-5">
                    <div class="rounded-2xl bg-[#212529] p-6 text-white shadow-sm">
                        @if(!empty($payload['specs_title']))
                            <h4 class="mb-4 text-xl font-bold">{{ $payload['specs_title'] }}</h4>
                        @endif
                        <ul class="mb-0 list-none p-0">
                            @foreach($specs as $index => $spec)
                                @php
                                    $iconClass = is_array($spec) ? trim((string) ($spec['icon_class'] ?? '')) : '';
                                    $label = is_array($spec) ? trim((string) ($spec['label'] ?? '')) : '';
                                    $value = is_array($spec) ? trim((string) ($spec['value'] ?? '')) : '';
                                    $isLast = $index === array_key_last($specs);
                                @endphp
                                @if($label !== '' || $value !== '')
                                    <li class="{{ $isLast ? 'flex justify-between' : 'mb-3 flex justify-between border-b border-[#6c757d] pb-2' }}">
                                        <span>
                                            @if($iconClass !== '')
                                                <i class="{{ $iconClass }} mr-2 text-[#933c1f]"></i>
                                            @endif
                                            {{ $label }}
                                        </span>
                                        <span>{{ $value }}</span>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
