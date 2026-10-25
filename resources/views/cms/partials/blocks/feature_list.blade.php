@php
    $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
@endphp

@if(!empty($payload['intro']))
    <div class="mt-4 leading-relaxed text-[#5c4a3d] cms-markdown">@cmsMarkdown($payload['intro'])</div>
@endif

@if($items !== [])
    <ul class="mt-3 list-disc space-y-1 pl-6 text-[#5c4a3d]">
        @foreach($items as $item)
            @php
                $text = is_array($item) ? (string) ($item['text'] ?? '') : (string) $item;
            @endphp
            @if(trim($text) !== '')
                <li>{{ $text }}</li>
            @endif
        @endforeach
    </ul>
@endif

