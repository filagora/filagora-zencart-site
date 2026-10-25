@php
    $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
@endphp

<section class="bg-[#dddddf] pb-12 sm:pb-16" data-home-reveal>
    <div class="mx-auto max-w-[1320px] px-6 md:px-10">
        @if(!empty($payload['title']))
            <h2 class="text-center text-5xl font-extrabold text-[#051a30]" data-home-reveal>{{ $payload['title'] }}</h2>
        @endif

        <div class="mt-14 grid gap-x-14 gap-y-12 lg:grid-cols-3">
            @foreach($items as $index => $item)
                @php
                    $headline = is_array($item) ? trim((string) ($item['headline'] ?? '')) : trim((string) $item);
                    $iconClass = is_array($item) ? trim((string) ($item['icon_class'] ?? '')) : '';
                    $url = is_array($item) ? trim((string) ($item['url'] ?? '')) : '';
                    $emphasize = is_array($item) ? (bool) ($item['emphasize'] ?? false) : false;
                    $delay = 40 + ($index * 60);
                    $itemClasses = $emphasize
                        ? 'flex items-center gap-6 rounded-[28px] border-[3px] border-[#a6461f] px-8 py-7'
                        : 'flex items-center gap-6';
                @endphp
                @if($headline !== '')
                    @if($url !== '')
                        <a href="{{ $url }}" class="{{ $itemClasses }}" data-home-reveal data-home-delay="{{ $delay }}">
                    @else
                        <div class="{{ $itemClasses }}" data-home-reveal data-home-delay="{{ $delay }}">
                    @endif
                        <span class="inline-flex h-16 w-16 items-center justify-center rounded-xl bg-[#a6461f] text-3xl text-white">
                            <i class="{{ $iconClass !== '' ? $iconClass : 'fa fa-chart-line' }}" aria-hidden="true"></i>
                        </span>
                        <span class="text-[38px] font-bold leading-tight text-[#111f2f]">{{ $headline }}</span>
                    @if($url !== '')
                        </a>
                    @else
                        </div>
                    @endif
                @endif
            @endforeach
        </div>
    </div>
</section>
