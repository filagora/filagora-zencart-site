@php
    $provider = trim((string) ($payload['provider'] ?? 'paypal'));
    $eyebrow = trim((string) ($payload['eyebrow'] ?? ''));
    $title = trim((string) ($payload['title'] ?? ''));
    $body = trim((string) ($payload['body'] ?? ''));
    $ctaLabel = trim((string) ($payload['cta_label'] ?? ''));
    $ctaUrl = trim((string) ($payload['cta_url'] ?? ''));
    $note = trim((string) ($payload['note'] ?? ''));
    $tag = trim((string) ($payload['tag'] ?? ''));
    // Only a plain alphanumeric ID may reach PayPal's SDK call below; anything else is ignored.
    $donateButtonId = trim((string) ($payload['donate_button_id'] ?? ''));
    $donateButtonId = preg_match('/^[A-Za-z0-9]{1,20}$/', $donateButtonId) === 1 ? $donateButtonId : '';
    $hasCtaLink = $ctaLabel !== '' && $ctaUrl !== '';
    $amountsHeading = \App\Support\DonationMethodText::for($payload, 'amounts_heading');
    $cardHeading = \App\Support\DonationMethodText::for($payload, 'card_heading');
    $amountSuffix = \App\Support\DonationMethodText::for($payload, 'amount_suffix');
    $tagLabel = \App\Support\DonationMethodText::for($payload, 'tag_label');
    $itemName = \App\Support\DonationMethodText::for($payload, 'item_name');
    $amounts = array_values(array_filter(is_array($payload['amounts'] ?? null) ? $payload['amounts'] : [], static fn (mixed $amount): bool => trim((string) $amount) !== ''));

    // Preset amounts are free text in the CMS ("5", "$7.50"); keep only positive numbers and
    // format them the way PayPal's a3 field expects ("5.00"). No amounts means no buttons.
    $donationAmounts = collect($amounts)
        ->map(static fn (mixed $amount): float => (float) preg_replace('/[^0-9.]/', '', (string) $amount))
        ->filter(static fn (float $amount): bool => $amount > 0)
        ->map(static fn (float $amount): array => [
            'value' => number_format($amount, 2, '.', ''),
            'label' => $amount == floor($amount) ? (string) (int) $amount : number_format($amount, 2, '.', ''),
        ])
        ->values()
        ->all();

    $providerConfig = match ($provider) {
        'zelle' => [
            'section' => 'bg-[color:var(--color-site-panel)] py-12 sm:py-16',
            'badge' => 'text-[#6d1ed4]',
            'card' => 'rounded-2xl border border-[#e6dccb] bg-white px-7 py-6 shadow-[0_14px_28px_rgba(15,33,51,0.06)]',
            'card_label' => 'text-[#7a6558]',
        ],
        'github' => [
            'section' => 'bg-[#fffdf5] py-12 sm:py-16',
            'badge' => 'text-[#24292f]',
            'card' => 'rounded-2xl border border-[#d0d7de] bg-white px-7 py-6 shadow-[0_14px_28px_rgba(15,33,51,0.06)]',
            'card_label' => 'text-[#57606a]',
        ],
        default => [
            'section' => 'bg-[#fffdf5] py-12 sm:py-16',
            'badge' => 'text-[#0f4c9b]',
            'card' => 'rounded-2xl border border-[#e6dccb] bg-white px-7 py-6 shadow-[0_14px_28px_rgba(15,33,51,0.06)]',
            'card_label' => 'text-[#7a6558]',
        ],
    };
@endphp

<section class="{{ $providerConfig['section'] }}">
    <div class="cms-page-shell !max-w-[1140px] !py-0">
        <div class="grid gap-8 lg:grid-cols-[minmax(240px,0.8fr)_minmax(0,1.25fr)] lg:items-start lg:gap-12">
            <div>
                @if($eyebrow !== '')
                    <p class="text-[0.72rem] font-bold uppercase tracking-[0.22em] text-[#9a4f12]">{{ $eyebrow }}</p>
                @endif

                @if($provider === 'paypal')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="33.5 6 98.5 27.5" class="mt-4 h-7 w-auto max-w-[126px]" aria-label="PayPal">
                        <path fill="#253B80" d="M46.2 6.8h-7.6c-.5 0-1 .4-1.1.9l-3.1 19.5c-.1.4.2.8.6.8h3.6c.5 0 1-.4 1.1-.9l.8-5.3c.1-.5.6-.9 1.1-.9h2.4c5 0 7.9-2.4 8.7-7.2.3-2.1 0-3.7-.9-4.9-1-1.3-2.8-2-5.6-2zm.9 7.1c-.4 2.7-2.5 2.7-4.5 2.7h-1.1l.8-5.1c0-.3.3-.5.6-.5h.5c1.4 0 2.7 0 3.3.8.4.5.5 1.2.4 2.1z"/>
                        <path fill="#253B80" d="M68.8 13.8h-3.6c-.3 0-.6.2-.6.5l-.2 1-.3-.4c-.8-1.2-2.7-1.6-4.5-1.6-4.2 0-7.8 3.2-8.5 7.6-.4 2.2.2 4.3 1.4 5.8 1.1 1.3 2.8 1.9 4.7 1.9 3.4 0 5.3-2.2 5.3-2.2l-.2 1c-.1.4.2.8.6.8h3.3c.5 0 1-.4 1.1-.9l2-12.7c.1-.4-.2-.8-.5-.8zm-5.1 7.4c-.4 2.1-2 3.6-4.2 3.6-1.1 0-1.9-.4-2.5-1-.5-.7-.7-1.6-.6-2.6.3-2.1 2-3.6 4.1-3.6 1.1 0 1.9.4 2.5 1 .6.6.8 1.6.7 2.6z"/>
                        <path fill="#253B80" d="M87.2 13.8h-3.7c-.4 0-.7.2-.9.5l-5.1 7.5-2.2-7.2c-.1-.5-.6-.8-1-.8h-3.6c-.5 0-.8.5-.6.9l4.1 12.1-3.9 5.5c-.3.4 0 1 .6 1h3.7c.4 0 .7-.2.9-.5l12.4-17.9c.3-.4 0-1.1-.7-1.1z"/>
                        <path fill="#179BD7" d="M98.5 6.8h-7.6c-.5 0-1 .4-1.1.9l-3.1 19.5c-.1.4.2.8.6.8h3.9c.4 0 .7-.3.7-.6l.9-5.6c.1-.5.6-.9 1.1-.9h2.4c5 0 7.9-2.4 8.7-7.2.3-2.1 0-3.7-.9-4.9-1-1.3-2.8-2-5.6-2zm.9 7.1c-.4 2.7-2.5 2.7-4.5 2.7h-1.1l.8-5.1c0-.3.3-.5.6-.5h.5c1.4 0 2.7 0 3.3.8.4.5.5 1.2.4 2.1z"/>
                        <path fill="#179BD7" d="M121.3 13.8h-3.6c-.3 0-.6.2-.6.5l-.2 1-.3-.4c-.8-1.2-2.7-1.6-4.5-1.6-4.2 0-7.8 3.2-8.5 7.6-.4 2.2.2 4.3 1.4 5.8 1.1 1.3 2.8 1.9 4.7 1.9 3.4 0 5.3-2.2 5.3-2.2l-.2 1c-.1.4.2.8.6.8h3.3c.5 0 1-.4 1.1-.9l2-12.7c.1-.4-.2-.8-.5-.8zm-5.1 7.4c-.4 2.1-2 3.6-4.2 3.6-1.1 0-1.9-.4-2.5-1-.5-.7-.7-1.6-.6-2.6.3-2.1 2-3.6 4.1-3.6 1.1 0 1.9.4 2.5 1 .6.6.8 1.6.7 2.6z"/>
                        <path fill="#179BD7" d="M126.9 7.2l-3.1 19.9c-.1.4.2.8.6.8h3.1c.5 0 1-.4 1.1-.9l3.1-19.5c.1-.4-.2-.8-.6-.8h-3.5c-.4-.1-.6.2-.7.5z"/>
                    </svg>
                @elseif($provider === 'zelle')
                    <p class="mt-4 text-3xl font-black {{ $providerConfig['badge'] }}">Zelle<sup class="align-super text-[0.45em]">&reg;</sup></p>
                @else
                    <div class="mt-4 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-[#24292f] text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-6 w-6" aria-label="GitHub" fill="currentColor">
                            <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
                        </svg>
                    </div>
                @endif

                @if($title !== '')
                    <h2 class="mt-4 text-3xl font-black text-[#1f1712]">{{ $title }}</h2>
                @endif

                @if($body !== '')
                    <div class="mt-4 max-w-md text-[0.99rem] leading-7 text-[#5c4a3d] cms-markdown">@cmsMarkdown($body)</div>
                @endif
            </div>

            <div @class([$providerConfig['card'], 'flex min-h-[10rem] flex-col items-center justify-center text-center lg:self-stretch' => $provider === 'paypal' && $donationAmounts === []])>
                @if($provider === 'paypal')
                    @if($donationAmounts !== [])
                        @if($amountsHeading !== '')
                            <p class="mb-4 text-xs font-bold uppercase tracking-[0.22em] text-[#8d5a3b]">{{ $amountsHeading }}</p>
                        @endif
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                            @foreach($donationAmounts as $donationAmount)
                                <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top" class="transition hover:-translate-y-0.5">
                                    <input type="hidden" name="cmd" value="_xclick-subscriptions">
                                    <input type="hidden" name="business" value="paypal@zen-cart.com">
                                    <input type="hidden" name="item_name" value="{{ $itemName }}">
                                    <input type="hidden" name="no_shipping" value="1">
                                    <input type="hidden" name="no_note" value="1">
                                    <input type="hidden" name="currency_code" value="USD">
                                    <input type="hidden" name="a3" value="{{ $donationAmount['value'] }}">
                                    <input type="hidden" name="p3" value="1">
                                    <input type="hidden" name="t3" value="M">
                                    <input type="hidden" name="src" value="1">
                                    <input type="hidden" name="sra" value="1">
                                    <button type="submit" class="flex w-full min-w-0 flex-col items-center justify-center rounded-xl border border-[#ead9c6] bg-[#fffaf4] px-2 py-3 text-[#2a1f16] transition hover:border-[#c85f1f]/40 hover:bg-[#fff3e5] hover:shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" aria-label="Donate ${{ $donationAmount['value'] }}{{ $amountSuffix !== '' ? ' ' . $amountSuffix : '' }} with PayPal">
                                        <span class="text-xl font-black leading-tight">${{ $donationAmount['label'] }}</span>
                                        @if($amountSuffix !== '')
                                            <span class="text-[0.7rem] font-semibold uppercase tracking-wide text-[#8d5a3b]">{{ $amountSuffix }}</span>
                                        @endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @endif

                    @if($donateButtonId !== '' || $hasCtaLink)
                        <div @class(['mt-7 border-t border-[#ead9c6] pt-6' => $donationAmounts !== []])>
                            @if($cardHeading !== '')
                                <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#8d5a3b]">{{ $cardHeading }}</p>
                            @endif
                            @if($donateButtonId !== '')
                                @php($donateButtonElementId = 'paypal-donate-button-' . ($block->id ?? $donateButtonId))
                                <div id="{{ $donateButtonElementId }}" @class(['mt-4' => $cardHeading !== ''])></div>
                                @once
                                    <script src="https://www.paypalobjects.com/donate/sdk/donate-sdk.js" charset="UTF-8"></script>
                                @endonce
                                <script>
                                    PayPal.Donation.Button({
                                        env: 'production',
                                        hosted_button_id: @js($donateButtonId),
                                        image: {
                                            src: 'https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif',
                                            alt: 'Donate with PayPal button',
                                            title: 'PayPal - The safer, easier way to pay online!',
                                        },
                                    }).render(@js('#' . $donateButtonElementId));
                                </script>
                                @if($hasCtaLink)
                                    <noscript>
                                        <a href="{{ $ctaUrl }}" target="_blank" rel="noopener noreferrer" class="ui-btn-primary !px-6 !py-3 text-sm">{{ $ctaLabel }}</a>
                                    </noscript>
                                @endif
                            @else
                                <a href="{{ $ctaUrl }}" target="_blank" rel="noopener noreferrer" @class(['ui-btn-primary !px-6 !py-3 text-sm', 'mt-4' => $cardHeading !== ''])>
                                    {{ $ctaLabel }}
                                </a>
                            @endif
                        </div>
                    @endif
                @elseif($provider === 'zelle')
                    @if($cardHeading !== '')
                        <p class="mb-4 text-xs font-bold uppercase tracking-[0.22em] text-[#8d5a3b]">{{ $cardHeading }}</p>
                    @endif
                    <div class="rounded-xl border border-[#ead9c6] bg-white px-6 py-5 shadow-sm">
                        @if($tagLabel !== '')
                            <p class="text-xs {{ $providerConfig['card_label'] }}">{{ $tagLabel }}</p>
                        @endif
                        <p @class(['text-4xl font-black text-[#211813]', 'mt-2' => $tagLabel !== ''])>{{ $tag }}</p>
                    </div>
                    @if($note !== '')
                        <div class="mt-5 text-sm leading-7 text-[#5c4a3d] cms-markdown">@cmsMarkdown($note)</div>
                    @endif
                @else
                    @if($cardHeading !== '')
                        <p class="mb-4 text-xs font-bold uppercase tracking-[0.22em] text-[#57606a]">{{ $cardHeading }}</p>
                    @endif
                    @if($ctaLabel !== '' && $ctaUrl !== '')
                        <a href="{{ $ctaUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-3 rounded-xl border border-[#d0d7de] bg-[#24292f] px-6 py-4 text-sm font-bold text-white transition hover:bg-[#1b1f24]">
                            <span>{{ $ctaLabel }}</span>
                            <i class="fa-solid fa-arrow-right-long" aria-hidden="true"></i>
                        </a>
                    @endif
                    @if($note !== '')
                        <div class="mt-5 text-sm leading-7 text-[#57606a] cms-markdown">@cmsMarkdown($note)</div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</section>
