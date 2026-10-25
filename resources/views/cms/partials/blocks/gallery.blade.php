@php
    $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
@endphp

@if($items !== [])
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($items as $item)
            @php
                $url = is_array($item) ? (string) ($item['url'] ?? '') : '';
                $alt = is_array($item) ? (string) ($item['alt'] ?? '') : '';
                $caption = is_array($item) ? (string) ($item['caption'] ?? '') : '';
            @endphp
            @if($url !== '')
                <figure>
                    <img src="{{ $url }}" alt="{{ $alt }}" class="w-full rounded-lg border border-[#d8d1c8]">
                    @if($caption !== '')
                        <figcaption class="mt-2 text-sm text-[#5c4a3d]">@cmsMarkdownInline($caption)</figcaption>
                    @endif
                </figure>
            @endif
        @endforeach
    </div>
@endif

