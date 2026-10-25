@if(!empty($payload['url']))
    <figure class="mt-4">
        <img src="{{ $payload['url'] }}" alt="{{ (string) ($payload['alt'] ?? '') }}" class="w-full rounded-lg border border-[#d8d1c8]">
        @if(!empty($payload['caption']))
            <figcaption class="mt-2 text-sm text-[#5c4a3d]">@cmsMarkdownInline($payload['caption'])</figcaption>
        @endif
    </figure>
@endif

