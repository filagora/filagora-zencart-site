@php
    $eyebrow = trim((string) ($payload['eyebrow'] ?? ''));
    $title = trim((string) ($payload['title'] ?? ''));
    $body = trim((string) ($payload['body'] ?? ''));
    $note = trim((string) ($payload['note'] ?? ''));
@endphp

<section class="bg-[color:var(--color-site-panel)] pb-12 pt-6 sm:pb-16 sm:pt-8">
    <div class="cms-page-shell !max-w-[1140px] !py-0">
        <div class="grid gap-10 lg:grid-cols-[minmax(0,1.45fr)_minmax(280px,0.95fr)] lg:items-center">
            <div>
                @if($eyebrow !== '')
                    <p class="inline-flex rounded-full border border-[#933c1f] bg-[#933c1f] px-5 py-1.5 text-sm font-extrabold uppercase tracking-[0.14em] text-white shadow-[0_8px_20px_rgba(147,60,31,0.28)]">
                        {{ $eyebrow }}
                    </p>
                @endif

                @if($title !== '')
                    <h1 class="mt-5 max-w-[13ch] text-[clamp(2.9rem,8vw,5rem)] font-black text-[#0f2133]">
                        {{ $title }}
                    </h1>
                @endif

                @if($body !== '')
                    <div class="mt-5 max-w-3xl text-[1.08rem] leading-8 text-[#435060] sm:text-[1.2rem] cms-markdown">
                        @cmsMarkdown($body)
                    </div>
                @endif
            </div>

            <aside class="border-t border-[#d9cbc2] pt-6 lg:border-l lg:border-t-0 lg:pl-8 lg:pt-0">
                <p class="text-sm font-extrabold uppercase tracking-[0.14em] text-[#933c1f]">Why It Matters</p>
                @if($note !== '')
                    <div class="mt-4 text-base leading-7 text-[#435060] cms-markdown">
                        @cmsMarkdown($note)
                    </div>
                @endif
                <div class="mt-6 flex items-start gap-4">
                    <span class="mt-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#933c1f] text-white">
                        <i class="fa-solid fa-heart" aria-hidden="true"></i>
                    </span>
                    <p class="text-sm font-semibold leading-6 text-[#435060]">
                        Community support, documentation, and core development all benefit directly.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</section>
