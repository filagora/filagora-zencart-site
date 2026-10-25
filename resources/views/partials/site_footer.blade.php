<footer class="border-t border-[var(--color-site-line)] bg-[color:var(--color-site-bg)] text-[color:var(--color-site-ink)]">
    <div class="mx-auto max-w-[1220px] px-6 py-12 md:px-10 md:py-14">
        <div class="mb-8 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div class="max-w-[540px]">
                <p class="ui-eyebrow">Built for Independent Commerce</p>
            </div>
        </div>

        <div class="grid gap-8 md:grid-cols-2 xl:grid-cols-12">
            @if(($footerGithubBlockHtml ?? '') !== '')
                {!! $footerGithubBlockHtml !!}
            @else
                <section class="xl:col-span-4">
                    <h3 class="ui-footer-heading">Github</h3>
                    <p class="mt-3 max-w-[420px] text-[0.98rem] leading-7 text-[color:var(--color-site-muted)]">
                        Zen Cart is in constant development. Please join us on Github and find the latest code there!
                    </p>
                    @if(($footerGithubLogoUrl ?? '') !== '')
                        <a class="ui-shell-panel mt-6 inline-flex rounded-2xl px-3.5 py-3" href="https://github.com/zencart/zencart" target="_blank" rel="noopener noreferrer">
                            <img class="h-auto w-[148px] max-w-full" src="{{ $footerGithubLogoUrl }}" alt="Zen Cart on Github">
                        </a>
                    @endif
                </section>
            @endif

            <section class="xl:col-span-2">
                <h3 class="ui-footer-heading">Useful Links</h3>
                <ul class="ui-footer-link-list mt-4">
                    @foreach(($footerUsefulMenuItems ?? []) as $item)
                        @continue(!is_string($item['url'] ?? null) || $item['url'] === '')
                        <li>
                            <a
                                class="transition hover:text-[color:var(--color-site-ink)]"
                                href="{{ $item['url'] }}"
                                target="{{ $item['target'] ?? '_self' }}"
                                @if(($item['target'] ?? '_self') === '_blank') rel="noopener noreferrer" @endif
                            >
                                {{ $item['label'] ?? 'Untitled' }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="xl:col-span-2">
                <h3 class="ui-footer-heading">Legal</h3>
                <ul class="ui-footer-link-list mt-4">
                    @foreach(($footerLegalMenuItems ?? []) as $item)
                        @continue(!is_string($item['url'] ?? null) || $item['url'] === '')
                        <li>
                            <a
                                class="transition hover:text-[color:var(--color-site-ink)]"
                                href="{{ $item['url'] }}"
                                target="{{ $item['target'] ?? '_self' }}"
                                @if(($item['target'] ?? '_self') === '_blank') rel="noopener noreferrer" @endif
                            >
                                {{ $item['label'] ?? 'Untitled' }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>

            @if(($footerNewsletterBlockHtml ?? '') !== '')
                {!! $footerNewsletterBlockHtml !!}
            @else
                <section class="xl:col-span-4">
                    <h3 class="ui-footer-heading">Newsletter</h3>
                    <p class="mt-3 max-w-[620px] text-[0.98rem] leading-7 text-[color:var(--color-site-muted)]">
                        When you sign up, you will receive a newsletter approximately every 2 months with news and updates about Zen Cart. We will not sell your email address or use it for any other purpose.
                    </p>
                    <a
                        class="ui-btn-secondary mt-5 !px-6 !py-3 !text-sm"
                        href="{{ route('newsletter.show') }}"
                    >
                        Click here to subscribe
                    </a>
                </section>
            @endif
        </div>
    </div>

    <div class="border-t border-[var(--color-site-line)] bg-[color:var(--color-site-panel)]">
        <div class="mx-auto max-w-[1220px] px-6 py-5 md:px-10">
            <p class="text-[13px] leading-relaxed text-[color:var(--color-site-muted)]">
                Content and Graphics Copyright (c) 2003 - {{ date('Y') }} Zen Ventures, LLC - all rights reserved
                Zen Cart<sup>TM</sup> is a  Trademark of Zen Ventures, LLC
            </p>
        </div>
    </div>
{{--  Spamtrap Honeypot link (created with/sends-to projecthoneypot.com) --}}
    <div style="display: none;"><a href="https://www.zen-cart.com/priestlyambiguous.php">trapezoid-twopenny</a></div>
</footer>

@if(($cookieConsentWorkflowEnabled ?? true) === true)
    @include('partials.cookie_consent')
@endif
