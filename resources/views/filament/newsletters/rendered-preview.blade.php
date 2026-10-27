@php
    /** @var ?string $previewUrl */
@endphp

@if (filled($previewUrl))
    <div class="space-y-3">
        <p class="text-sm leading-6 text-[#52616f]">
            This preview uses the rendered newsletter email template.
            <a href="{{ $previewUrl }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#8a4b19]">
                Open full preview in a new tab
            </a>
        </p>

        <iframe
            src="{{ $previewUrl }}"
            title="Newsletter preview"
            class="min-h-[780px] w-full rounded-xl border border-[#d6d3d1] bg-white"
            loading="lazy"
        ></iframe>
    </div>
@endif
