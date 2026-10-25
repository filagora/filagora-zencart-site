@php
    $cookieConsentGroups = is_array($cookieConsentGroups ?? null) ? $cookieConsentGroups : [];
@endphp

<aside class="fixed bottom-4 left-4 right-4 z-[1100] mx-auto max-w-4xl rounded-2xl border border-[#cabca8] bg-[#ececed] p-4 shadow-lg" data-cookie-banner data-cookie-policy-version="{{ $activeCookiePolicyVersion ?? '' }}" hidden>
    <h3 class="text-lg font-semibold text-[#0f2133]">Cookie Preferences</h3>
    <p class="mt-1 text-sm text-[#5c6570]">We use cookies to run the site and, with your consent, improve analytics and content relevance.</p>
    <div class="mt-3 rounded-xl border border-[#d9a36f] bg-[#f7e8df] px-3 py-2 text-sm text-[#7f3219]" data-cookie-reconsent-notice hidden>
        Our cookie policy has been updated, so we need you to review your preferences and consent again.
    </div>
    @if(is_string($cookiePolicyPageUrl ?? null) && $cookiePolicyPageUrl !== '')
        <p class="mt-2 text-sm text-[#5c6570]">
            <a class="font-semibold text-[#8f3f1f] underline underline-offset-2 transition hover:text-[#7f3219]" href="{{ $cookiePolicyPageUrl }}" target="_blank" rel="noopener noreferrer">Read our cookie policy</a>
        </p>
    @endif
    <div class="mt-3 flex flex-wrap items-center gap-2">
        <button type="button" class="inline-flex items-center rounded-lg border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-cookie-accept-all>Accept all</button>
        <button type="button" class="inline-flex items-center rounded-lg border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-cookie-reject>Reject optional</button>
        <button type="button" class="inline-flex items-center rounded-lg border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-cookie-manage>Manage</button>
    </div>
</aside>

<button type="button" class="fixed bottom-4 right-4 z-[1090] inline-flex h-11 w-11 items-center justify-center rounded-full border border-[#cabca8] bg-white text-[#0f2133] shadow-md transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-cookie-open-settings aria-label="Cookie settings" title="Cookie settings">
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path fill="currentColor" d="M12 2a9.99 9.99 0 0 0-9.8 12h.6a3.2 3.2 0 1 1 0 6.4h-.1A10 10 0 1 0 12 2Zm-5 8.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm5-4a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Zm4.5 4.75a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5ZM13 19a2 2 0 1 1 0-4 2 2 0 0 1 0 4Z"/>
    </svg>
</button>

<div class="fixed inset-0 z-[1200] flex items-center justify-center bg-[rgba(12,10,8,0.58)] p-4" data-cookie-modal data-cookie-policy-version="{{ $activeCookiePolicyVersion ?? '' }}" hidden>
    <section class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-[#cabca8] bg-[#ececed] p-4 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="cookie-modal-title">
        <div class="mb-4 flex items-center justify-between gap-3 border-b border-[#d8d1c8] pb-3">
            <h3 id="cookie-modal-title" class="text-lg font-semibold text-[#0f2133]">Manage cookie preferences</h3>
            <button type="button" class="inline-flex items-center rounded-lg border border-[#cabca8] bg-white px-3 py-1.5 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-cookie-close>Close</button>
        </div>

        <div class="mb-4 rounded-xl border border-[#d9a36f] bg-[#f7e8df] px-3 py-2 text-sm text-[#7f3219]" data-cookie-reconsent-notice hidden>
            Our cookie policy has been updated, so we need you to review your preferences and consent again.
        </div>

        @if(is_string($cookiePolicyPageUrl ?? null) && $cookiePolicyPageUrl !== '')
            <p class="mb-4 text-sm text-[#5c6570]">
                <a class="font-semibold text-[#8f3f1f] underline underline-offset-2 transition hover:text-[#7f3219]" href="{{ $cookiePolicyPageUrl }}" target="_blank" rel="noopener noreferrer">Read our cookie policy</a>
            </p>
        @endif

        @foreach($cookieConsentGroups as $group)
            @php
                $key = (string) ($group['key'] ?? '');
                $label = (string) ($group['label'] ?? $key);
                $description = (string) ($group['description'] ?? '');
                $required = (bool) ($group['required'] ?? false);
                $defaultEnabled = (bool) ($group['defaultEnabled'] ?? false);
                $cookies = is_array($group['cookies'] ?? null) ? $group['cookies'] : [];
            @endphp
            @continue($key === '')

            <details class="mb-3 rounded-xl border border-[#cabca8] bg-white p-3" data-cookie-group-details {{ $required ? 'open' : '' }}>
                <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                    {{-- <summary> only permits phrasing content, not <div>/<p> - span+block
                    keeps the exact same layout while staying spec-valid (w3.org/nu validator). --}}
                    <span class="flex items-start justify-between gap-3">
                        <span class="inline-flex items-center gap-2">
                            <strong>{{ $label }}</strong>
                            @if($required)
                                <span class="inline-flex items-center rounded-full border border-[#933c1f] bg-[#f7e8df] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.03em] text-[#933c1f]">Required</span>
                            @endif
                        </span>
                        <input
                            type="checkbox"
                            id="cookie-group-{{ $key }}"
                            data-cookie-group-checkbox
                            data-cookie-group-key="{{ $key }}"
                            data-cookie-required="{{ $required ? '1' : '0' }}"
                            data-cookie-default-enabled="{{ $defaultEnabled ? '1' : '0' }}"
                            @if($required) checked disabled @endif
                        >
                    </span>
                    @if($description !== '')
                        <span class="mt-2 block text-sm text-[#5c6570]">{{ $description }}</span>
                    @endif
                </summary>

                @if($cookies !== [])
                    <div class="mt-3 grid gap-2">
                        @foreach($cookies as $cookie)
                            @php
                                $cookieKey = (string) ($cookie['key'] ?? '');
                                $cookieLabel = (string) ($cookie['label'] ?? $cookieKey);
                                $cookieDescription = (string) ($cookie['description'] ?? '');
                                $cookieProvider = (string) ($cookie['provider'] ?? '');
                                $cookieRetention = (string) ($cookie['retentionPeriod'] ?? '');
                                $cookieRequired = (bool) ($cookie['required'] ?? $required);
                                $cookieDefaultEnabled = (bool) ($cookie['defaultEnabled'] ?? $defaultEnabled);
                            @endphp
                            @continue($cookieKey === '')

                            {{-- <label> only permits phrasing content, not <div>/<p> - span+block
                            keeps the exact same layout while staying spec-valid (w3.org/nu validator). --}}
                            <label class="rounded-lg border border-[#d8d1c8] bg-[#f8f6f1] p-3" for="cookie-item-{{ $key }}-{{ md5($cookieKey) }}">
                                <span class="flex items-start justify-between gap-3">
                                    <strong>{{ $cookieLabel }}</strong>
                                    <input
                                        type="checkbox"
                                        id="cookie-item-{{ $key }}-{{ md5($cookieKey) }}"
                                        data-cookie-checkbox
                                        data-cookie-key="{{ $cookieKey }}"
                                        data-cookie-group-key="{{ $key }}"
                                        data-cookie-required="{{ $cookieRequired ? '1' : '0' }}"
                                        data-cookie-default-enabled="{{ $cookieDefaultEnabled ? '1' : '0' }}"
                                        @if($cookieRequired) checked disabled @endif
                                    >
                                </span>
                                @if($cookieDescription !== '')
                                    <span class="mt-2 block text-sm text-[#5c6570]">{{ $cookieDescription }}</span>
                                @endif
                                <span class="mt-2 inline-flex flex-wrap gap-3 text-xs text-[#5c6570]">
                                    @if($cookieProvider !== '')
                                        <span><strong>Provider:</strong> {{ $cookieProvider }}</span>
                                    @endif
                                    @if($cookieRetention !== '')
                                        <span><strong>Retention:</strong> {{ $cookieRetention }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </details>
        @endforeach

        <div class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-[#d8d1c8] pt-3">
            <button type="button" class="inline-flex items-center rounded-lg border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-cookie-reject>Reject optional</button>
            <button type="button" class="inline-flex items-center rounded-lg border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-cookie-save>Save preferences</button>
        </div>
    </section>
</div>
