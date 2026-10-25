@php
    $paragraphs = is_array($payload['paragraphs'] ?? null) ? $payload['paragraphs'] : [];
    $checklistItems = is_array($payload['checklist_items'] ?? null) ? $payload['checklist_items'] : [];
    $asideRows = is_array($payload['aside_rows'] ?? null) ? $payload['aside_rows'] : [];
    $sectionTone = trim((string) ($payload['section_tone'] ?? 'cream'));
    $asideType = trim((string) ($payload['aside_type'] ?? 'card'));
    $asideTheme = trim((string) ($payload['aside_theme'] ?? 'dark'));
    $imagePosition = trim((string) ($payload['image_position'] ?? 'end'));
    $imageUrl = trim((string) ($payload['image_url'] ?? ''));
    $imageAlt = trim((string) ($payload['image_alt'] ?? ''));
    $sectionClasses = match ($sectionTone) {
        'gray' => 'bg-[#ddd] py-12 sm:py-16',
        default => 'bg-[#fffff0] py-12 sm:py-16',
    };
    $textColumnClasses = $imagePosition === 'start'
        ? 'order-1 motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none lg:order-2 lg:col-span-7 lg:pl-12'
        : 'motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none lg:col-span-7 lg:pr-12';
    $asideColumnClasses = $imagePosition === 'start'
        ? 'order-2 motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none lg:order-1 lg:col-span-5'
        : 'motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none lg:col-span-5';
    $cardClasses = $asideTheme === 'light'
        ? 'rounded-2xl bg-white p-6 shadow-sm'
        : 'rounded-2xl bg-[#212529] p-6 text-white shadow-sm';
    $rowBorderClasses = $asideTheme === 'light' ? 'border-b pb-2' : 'border-b border-[#6c757d] pb-2';
    $mutedValueClass = $asideTheme === 'light' ? 'text-right text-[#6c757d]' : '';
@endphp

<section class="{{ $sectionClasses }}">
    <div class="mx-auto w-full max-w-[1140px] px-4">
        <div class="grid grid-cols-1 gap-y-8 lg:grid-cols-12 lg:items-center">
            <div class="{{ $textColumnClasses }}">
                @if(!empty($payload['eyebrow']))
                    <div class="ui-section-kicker mb-2 text-[#933c1f] uppercase font-bold">{{ $payload['eyebrow'] }}</div>
                @endif
                @if(!empty($payload['title']))
                    <h3 class="mb-3 text-4xl font-bold">{{ $payload['title'] }}</h3>
                @endif
                @foreach($paragraphs as $paragraph)
                    @php
                        $text = is_array($paragraph) ? trim((string) ($paragraph['text'] ?? '')) : trim((string) $paragraph);
                    @endphp
                    @if($text !== '')
                        <div class="mb-3 cms-markdown">@cmsMarkdown($text)</div>
                    @endif
                @endforeach
                @if($checklistItems !== [])
                    <div class="ui-checklist-wrap">
                        <ul class="ui-checklist">
                            @foreach($checklistItems as $item)
                                @php
                                    $text = is_array($item) ? trim((string) ($item['text'] ?? '')) : trim((string) $item);
                                @endphp
                                @if($text !== '')
                                    <li><i class="fa-solid fa-circle-check"></i> {{ $text }}</li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="{{ $asideColumnClasses }}">
                @if($asideType === 'image' && $imageUrl !== '')
                    <div class="overflow-hidden rounded-2xl shadow-sm">
                        <img src="{{ $imageUrl }}" alt="{{ $imageAlt !== '' ? $imageAlt : 'Comparison illustration' }}" class="w-full">
                    </div>
                @elseif($asideRows !== [])
                    <div class="{{ $cardClasses }}">
                        @if(!empty($payload['aside_title']))
                            <h4 class="mb-4 text-xl font-bold">{{ $payload['aside_title'] }}</h4>
                        @endif
                        <ul class="list-none space-y-3 p-0 {{ $asideTheme === 'light' ? 'text-[#212529]' : '' }}">
                            @foreach($asideRows as $index => $row)
                                @php
                                    $iconClass = is_array($row) ? trim((string) ($row['icon_class'] ?? '')) : '';
                                    $label = is_array($row) ? trim((string) ($row['label'] ?? '')) : '';
                                    $value = is_array($row) ? trim((string) ($row['value'] ?? '')) : '';
                                    $isLast = $index === array_key_last($asideRows);
                                @endphp
                                @if($label !== '' || $value !== '')
                                    <li class="flex items-center justify-between {{ $isLast ? '' : $rowBorderClasses }}">
                                        <span class="{{ $asideTheme === 'light' ? 'font-bold' : '' }}">
                                            @if($iconClass !== '')
                                                <i class="{{ $iconClass }} mr-2 text-[#933c1f]"></i>
                                            @endif
                                            {{ $label }}
                                        </span>
                                        <span class="{{
                                            $asideTheme === 'light' && $value !== '' ? $mutedValueClass : ''
                                        }}">{!! nl2br(e($value)) !!}</span>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
