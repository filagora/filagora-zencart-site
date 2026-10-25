@if(($enabled ?? false) && filled($siteKey ?? null))
    @php
        $widgetKey = 'turnstile-'.md5((string) $statePath);
        $statePathString = (string) $statePath;
    @endphp

    <div class="space-y-2" data-turnstile-state-path="{{ $statePathString }}">
        <div
            wire:ignore
            x-data="turnstileWidget({
                statePath: @js($statePathString),
                siteKey: @js((string) $siteKey),
            })"
            x-init="init()"
            x-on:turnstile:reset.window="if ($event.detail?.statePath === statePath) reset()"
            wire:key="{{ $widgetKey }}"
        >
            <div x-ref="widget"></div>
        </div>

        <p class="text-xs text-gray-600">This site is protected by Cloudflare Turnstile.</p>

        @error($statePathString)
            <p data-validation-error class="fi-fo-field-wrp-error-message">{{ $message }}</p>
        @enderror
    </div>

    @once
        <script>
            window.turnstileWidget = window.turnstileWidget || function (config) {
                return {
                    statePath: config.statePath,
                    siteKey: config.siteKey,
                    widgetId: null,
                    pollHandle: null,

                    init() {
                        this.setToken('');
                        this.ensureScript();
                        this.waitForTurnstile();
                    },

                    ensureScript() {
                        if (document.querySelector('script[data-turnstile-script]')) {
                            return;
                        }

                        const script = document.createElement('script');
                        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
                        script.async = true;
                        script.defer = true;
                        script.setAttribute('data-turnstile-script', 'true');
                        document.head.appendChild(script);
                    },

                    waitForTurnstile() {
                        if (window.turnstile?.render) {
                            this.render();

                            return;
                        }

                        this.pollHandle = window.setInterval(() => {
                            if (! window.turnstile?.render) {
                                return;
                            }

                            window.clearInterval(this.pollHandle);
                            this.pollHandle = null;
                            this.render();
                        }, 100);
                    },

                    render() {
                        if (this.widgetId !== null || ! this.$refs.widget) {
                            return;
                        }

                        this.widgetId = window.turnstile.render(this.$refs.widget, {
                            sitekey: this.siteKey,
                            callback: (token) => this.setToken(token),
                            'expired-callback': () => this.setToken(''),
                            'error-callback': () => this.setToken(''),
                        });
                    },

                    reset() {
                        this.setToken('');

                        if (this.widgetId !== null && window.turnstile?.reset) {
                            window.turnstile.reset(this.widgetId);
                        }
                    },

                    setToken(token) {
                        this.$wire.set(this.statePath, token);
                    },
                };
            };
        </script>
    @endonce
@endif
