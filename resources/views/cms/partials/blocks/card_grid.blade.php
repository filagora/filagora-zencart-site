@php
    $cards = is_array($payload['cards'] ?? null) ? $payload['cards'] : [];
@endphp

@if($cards !== [])
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        @foreach($cards as $card)
            @php
                $title = is_array($card) ? (string) ($card['title'] ?? '') : '';
                $body = is_array($card) ? (string) ($card['body'] ?? '') : '';
                $url = is_array($card) ? (string) ($card['url'] ?? '') : '';
            @endphp
            <article class="rounded-lg border border-[#d8d1c8] bg-[#ececed] p-4">
                @if($title !== '')
                    <h3 class="text-lg font-bold text-[#251b12]">{{ $title }}</h3>
                @endif
                @if($body !== '')
                    <div class="mt-2 text-sm leading-relaxed text-[#5c4a3d] cms-markdown">@cmsMarkdown($body)</div>
                @endif
                @if($url !== '')
                    <p class="mt-3">
                        <a href="{{ $url }}" class="text-sm font-semibold text-[#8f3f1f] hover:text-[#6f2f16]">Learn more</a>
                    </p>
                @endif
            </article>
        @endforeach
    </div>
@endif

