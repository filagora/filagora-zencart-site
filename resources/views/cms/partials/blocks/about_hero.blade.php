@php
    $eyebrow = trim((string) ($payload['eyebrow'] ?? ''));
    $title = trim((string) ($payload['title'] ?? ''));
    $body = trim((string) ($payload['body'] ?? ''));
@endphp

<section class="bg-[color:var(--color-site-panel)] py-12 sm:py-16">
    <div class="cms-page-shell !max-w-[1140px] !py-0">
        <div class="mx-auto flex max-w-4xl flex-col items-center text-center">
            @if($eyebrow !== '')
                <p class="inline-flex rounded-full border border-[#933c1f] bg-[#933c1f] px-5 py-1.5 text-sm font-extrabold uppercase tracking-[0.14em] text-white shadow-[0_8px_20px_rgba(147,60,31,0.28)]">
                    {{ $eyebrow }}
                </p>
            @endif
            @if($title !== '')
                <h1 class="mt-5 text-[clamp(2.9rem,8vw,5rem)] font-black tracking-[-0.05em] text-[#0f2133]">
                    {{ $title }}
                </h1>
            @endif
            @if($body !== '')
                <div class="mt-5 max-w-3xl text-[1.08rem] leading-8 text-[#435060] sm:text-[1.2rem] cms-markdown">
                    @cmsMarkdown($body)
                </div>
            @endif
        </div>
    </div>
</section>
