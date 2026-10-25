@if($enabled ?? false)
    @php
        $siteKeyValue = (string) ($siteKey ?? '');
        $inputName = $inputName ?? 'turnstile_token';
        $widgetId = 'turnstile-widget-' . md5($inputName);
    @endphp

    @if(filled($siteKeyValue))
        <div class="space-y-2">
            <div id="{{ $widgetId }}" data-turnstile-widget data-turnstile-input="{{ $inputName }}" data-turnstile-site-key="{{ $siteKeyValue }}"></div>
            <p class="text-xs text-[#5c6570]">This site is protected by Cloudflare Turnstile.</p>
            @if ($errors->has($inputName))
                <p class="text-sm text-[#dc3545]">{{ $errors->first($inputName) }}</p>
            @endif
        </div>

        @once
            <script>
                (() => {
                    const ensureScript = () => {
                        if (document.querySelector('script[data-turnstile-script]')) {
                            return;
                        }

                        const script = document.createElement('script');
                        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
                        script.async = true;
                        script.defer = true;
                        script.setAttribute('data-turnstile-script', 'true');
                        document.head.appendChild(script);
                    };

                    const renderWidget = (container) => {
                        const inputName = container.dataset.turnstileInput;
                        const siteKey = container.dataset.turnstileSiteKey;
                        const input = document.querySelector(`input[name="${inputName}"]`);

                        if (!(input instanceof HTMLInputElement) || container.dataset.turnstileRendered === '1') {
                            return;
                        }

                        window.turnstile.render(container, {
                            sitekey: siteKey,
                            callback: (token) => { input.value = token; },
                            'expired-callback': () => { input.value = ''; },
                            'error-callback': () => { input.value = ''; },
                        });

                        container.dataset.turnstileRendered = '1';
                    };

                    const renderAllWidgets = () => {
                        document.querySelectorAll('[data-turnstile-widget]').forEach(renderWidget);
                    };

                    const waitForTurnstile = () => {
                        if (window.turnstile?.render) {
                            renderAllWidgets();
                            return;
                        }

                        window.setTimeout(waitForTurnstile, 100);
                    };

                    ensureScript();
                    waitForTurnstile();
                })();
            </script>
        @endonce
    @endif
@endif
