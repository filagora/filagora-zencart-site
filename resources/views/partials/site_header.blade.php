<header class="sticky top-0 z-[1100] border-b border-[var(--color-site-line)] bg-[color:var(--color-site-bg)]/95 backdrop-blur-sm">
    @php
        $headerItems = array_values($headerMenuItems ?? []);
    @endphp

    <div class="mx-auto flex max-w-[1600px] flex-wrap items-center justify-between gap-4 px-5 py-4 md:px-8 xl:px-10">
        <a href="/" class="flex shrink-0 items-center gap-3 sm:gap-4">
            {{-- Below Tailwind's sm breakpoint (640px) the wide logo is cramped next to the
                 header icons, so phones get a taller stacked version of it whose tagline is
                 big enough to read. The source carries its own width/height so the browser
                 reserves the right box before it loads. --}}
            <picture>
                <source media="(max-width: 639px)" srcset="{{ asset('assets/images/logo/ZCLogoStacked240t.png') }}" width="206" height="240">
                <img src="{{ asset('assets/images/logo/zen-cart-logo.png') }}" alt="Zen Cart Logo" width="254" height="68" fetchpriority="high" class="h-[80px] w-auto sm:h-[64px] xl:h-[70px]">
            </picture>
        </a>
        {{--  Spamtrap Honeypot link (created with/sends-to projecthoneypot.com). aria-hidden
              because this is deliberately not meant for any real visitor - sighted or
              assistive-tech - to perceive; display:none alone didn't stop Lighthouse's
              link-name audit from flagging it as a link with no discernible name. --}}
        <div style="display: none;" aria-hidden="true"><a href="https://www.zen-cart.com/priestlyambiguous.php">trapezoid-twopenny</a></div>

        <nav class="hidden items-center gap-5 xl:flex">
            @php $renderedHeaderItems = 0; @endphp
            @foreach($headerItems as $item)
                @php
                    $itemTarget = $item['target'] ?? '_self';
                    $itemUrl = is_string($item['url'] ?? null) ? $item['url'] : '';
                    $itemPath = is_string($itemUrl) ? (string) parse_url($itemUrl, PHP_URL_PATH) : '';
                    $itemPattern = ltrim($itemPath, '/');
                    $itemChildren = is_array($item['children'] ?? null) ? $item['children'] : [];
                    $isActive = $itemTarget === '_self'
                        && $itemPath !== ''
                        && str_starts_with($itemPath, '/')
                        && ($itemPath === '/'
                            ? request()->getPathInfo() === '/'
                            : request()->is($itemPattern) || request()->is($itemPattern.'/*'));
                @endphp
                @continue($itemChildren === [] && $itemUrl === '')
                @if($renderedHeaderItems > 0)
                    <span class="h-4 w-px bg-[color:var(--color-site-line)]" aria-hidden="true"></span>
                @endif
                @php $renderedHeaderItems++; @endphp
                @if($itemChildren !== [])
                    <details class="group relative" data-docs-menu>
                        <summary class="ui-nav-link flex cursor-pointer list-none items-center gap-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--color-site-brand)]">
                            {{ $item['label'] ?? 'Untitled' }}
                            <span class="text-sm transition group-open:rotate-180">⌄</span>
                        </summary>
                        <div class="absolute left-0 top-full z-20 min-w-[260px] pt-3">
                            <div class="ui-shell-panel rounded-2xl p-3">
                                @foreach($itemChildren as $child)
                                    @continue(!is_string($child['url'] ?? null) || $child['url'] === '')
                                    @php
                                        $groupLinks = is_array($child['children'] ?? null) ? $child['children'] : [];
                                    @endphp
                                    @if($groupLinks !== [])
                                        {{-- A dropdown link with links of its own is a group: its label as a heading
                                             (a link only when it has a real URL), the links indented beneath. --}}
                                        <div role="group" aria-label="{{ $child['label'] ?? 'Untitled' }}" class="{{ $loop->first ? '' : 'mt-3 ' }}px-4 py-2">
                                            @if($child['url'] !== '#')
                                                <a
                                                    href="{{ $child['url'] }}"
                                                    target="{{ $child['target'] ?? '_self' }}"
                                                    @if(($child['target'] ?? '_self') === '_blank') rel="noopener noreferrer" @endif
                                                    class="block text-base font-semibold text-[color:var(--color-site-ink)] transition hover:text-[color:var(--color-site-brand-text)]"
                                                >{{ $child['label'] ?? 'Untitled' }}</a>
                                            @else
                                                <div class="text-xs font-semibold uppercase tracking-[0.08em] text-[color:var(--color-site-muted)]">{{ $child['label'] ?? 'Untitled' }}</div>
                                            @endif
                                            @foreach($groupLinks as $groupLink)
                                                @continue(!is_string($groupLink['url'] ?? null) || $groupLink['url'] === '')
                                                <a
                                                    href="{{ $groupLink['url'] }}"
                                                    target="{{ $groupLink['target'] ?? '_self' }}"
                                                    @if(($groupLink['target'] ?? '_self') === '_blank') rel="noopener noreferrer" @endif
                                                    class="mt-1 block rounded-xl py-2 pl-4 pr-4 text-[0.95rem] font-medium text-[color:var(--color-site-ink)] transition hover:bg-white/80 hover:text-[color:var(--color-site-brand-text)]"
                                                >{{ $groupLink['label'] ?? 'Untitled' }}</a>
                                            @endforeach
                                        </div>
                                    @else
                                        <a
                                            href="{{ $child['url'] }}"
                                            target="{{ $child['target'] ?? '_self' }}"
                                            @if(($child['target'] ?? '_self') === '_blank') rel="noopener noreferrer" @endif
                                            class="{{ $loop->first ? '' : 'mt-1 ' }}block rounded-xl px-4 py-3 text-base font-semibold text-[color:var(--color-site-ink)] transition hover:bg-white/80 hover:text-[color:var(--color-site-brand-text)]"
                                        >{{ $child['label'] ?? 'Untitled' }}</a>
                                    @endif

                                @endforeach
                            </div>
                        </div>
                    </details>
                @else
                        <a
                            href="{{ $itemUrl }}"
                            class="ui-nav-link focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--color-site-brand)] {{ $isActive ? 'is-active' : '' }}"
                            target="{{ $itemTarget }}"
                        @if($isActive) aria-current="page" @endif
                        @if($itemTarget === '_blank') rel="noopener noreferrer" @endif
                    >{{ $item['label'] ?? 'Untitled' }}</a>
                @endif
            @endforeach
        </nav>

        {{-- ml-auto keeps this flush-right when it wraps to its own line
             below xl (nav is hidden there, so it's just logo + icons,
             instead of the hamburger (order-last below) splitting off alone
             with no anchor of its own). At xl+ the nav sits between this and
             the logo, so ml-auto is cancelled there and justify-between on
             the row centers the nav in the space between them instead. --}}
        <div class="ml-auto flex items-center gap-2.5 xl:ml-0">
        <div class="group relative order-last xl:hidden" data-mobile-nav>
            <button type="button" class="flex cursor-pointer list-none items-center justify-center rounded-full border border-[var(--color-site-line)] bg-[color:var(--color-site-panel)] p-3 text-[color:var(--color-site-ink)] transition hover:border-[color:var(--color-site-brand-text)] hover:text-[color:var(--color-site-brand-text)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--color-site-brand)]" aria-label="Toggle site navigation" aria-expanded="false" aria-controls="mobile-nav-panel" data-mobile-nav-trigger>
                <span class="sr-only">Open navigation menu</span>
                <span class="flex h-5 w-5 flex-col items-center justify-center gap-[3px]" aria-hidden="true">
                    <span class="block h-0.5 w-5 rounded-full bg-current transition-transform duration-200 group-[.is-open]:translate-y-[5px] group-[.is-open]:rotate-45"></span>
                    <span class="block h-0.5 w-5 rounded-full bg-current transition-opacity duration-200 group-[.is-open]:opacity-0"></span>
                    <span class="block h-0.5 w-5 rounded-full bg-current transition-transform duration-200 group-[.is-open]:-translate-y-[5px] group-[.is-open]:-rotate-45"></span>
                </span>
            </button>
            <div id="mobile-nav-panel" class="ui-shell-panel fixed right-4 top-[88px] z-[100000] min-w-[min(320px,calc(100vw-2.5rem))] rounded-2xl p-3" hidden data-mobile-nav-panel>
                <form method="get" action="{{ route('search.index') }}" class="mb-2 md:hidden">
                    <label for="mobile-global-search" class="sr-only">Search site</label>
                    <div class="flex items-center gap-2 rounded-xl border border-[var(--color-site-line)] bg-white px-3 py-2">
                        <input id="mobile-global-search" type="search" name="q" value="{{ is_scalar(request('q')) ? request('q') : '' }}" placeholder="Search..." class="w-full bg-transparent text-sm text-[color:var(--color-site-ink)] outline-none placeholder:text-[color:var(--color-site-muted)]">
                        <label for="mobile-global-search-type" class="sr-only">Search type</label>
                        <select id="mobile-global-search-type" name="type" class="shrink-0 border-l border-[var(--color-site-line)] bg-transparent pl-2 text-sm text-[color:var(--color-site-muted)] outline-none">
                            <option value="">All</option>
                            @foreach(\App\SearchResultType::cases() as $type)
                                <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="cursor-pointer text-sm font-semibold text-[color:var(--color-site-brand-text)] transition hover:text-[color:var(--color-site-brand-dark)]">Go</button>
                    </div>
                </form>
                @foreach($headerItems as $item)
                    @php
                        $itemTarget = $item['target'] ?? '_self';
                        $itemUrl = is_string($item['url'] ?? null) ? $item['url'] : '';
                        $itemPath = is_string($itemUrl) ? (string) parse_url($itemUrl, PHP_URL_PATH) : '';
                        $itemPattern = ltrim($itemPath, '/');
                        $itemChildren = is_array($item['children'] ?? null) ? $item['children'] : [];
                        $isActive = $itemTarget === '_self'
                            && $itemPath !== ''
                            && str_starts_with($itemPath, '/')
                            && ($itemPath === '/'
                                ? request()->getPathInfo() === '/'
                                : request()->is($itemPattern) || request()->is($itemPattern.'/*'));
                    @endphp
                    @continue($itemChildren === [] && $itemUrl === '')
                    @if($itemChildren !== [])
                        <div class="mt-1 rounded-xl border border-[var(--color-site-line)] bg-white/60 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.08em] text-[color:var(--color-site-muted)]">{{ $item['label'] ?? 'Untitled' }}</div>
                            @foreach($itemChildren as $child)
                                @continue(!is_string($child['url'] ?? null) || $child['url'] === '')
                                @php
                                    $groupLinks = is_array($child['children'] ?? null) ? $child['children'] : [];
                                @endphp
                                @if($groupLinks !== [])
                                    <div role="group" aria-label="{{ $child['label'] ?? 'Untitled' }}" class="mt-3">
                                        @if($child['url'] !== '#')
                                            <a
                                                href="{{ $child['url'] }}"
                                                target="{{ $child['target'] ?? '_self' }}"
                                                @if(($child['target'] ?? '_self') === '_blank') rel="noopener noreferrer" @endif
                                                class="block text-base font-semibold text-[color:var(--color-site-ink)] transition hover:text-[color:var(--color-site-brand-text)]"
                                            >{{ $child['label'] ?? 'Untitled' }}</a>
                                        @else
                                            <div class="text-base font-semibold text-[color:var(--color-site-ink)]">{{ $child['label'] ?? 'Untitled' }}</div>
                                        @endif
                                        @foreach($groupLinks as $groupLink)
                                            @continue(!is_string($groupLink['url'] ?? null) || $groupLink['url'] === '')
                                            <a
                                                href="{{ $groupLink['url'] }}"
                                                target="{{ $groupLink['target'] ?? '_self' }}"
                                                @if(($groupLink['target'] ?? '_self') === '_blank') rel="noopener noreferrer" @endif
                                                class="mt-1 block pl-4 text-[0.95rem] font-medium text-[color:var(--color-site-ink)] transition hover:text-[color:var(--color-site-brand-text)]"
                                            >{{ $groupLink['label'] ?? 'Untitled' }}</a>
                                        @endforeach
                                    </div>
                                @else
                                    <a
                                        href="{{ $child['url'] }}"
                                        target="{{ $child['target'] ?? '_self' }}"
                                        @if(($child['target'] ?? '_self') === '_blank') rel="noopener noreferrer" @endif
                                        class="mt-2 block text-base font-semibold text-[color:var(--color-site-ink)] transition hover:text-[color:var(--color-site-brand-text)]"
                                    >{{ $child['label'] ?? 'Untitled' }}</a>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <a
                            href="{{ $itemUrl }}"
                            class="mt-1 block rounded-xl px-4 py-3 text-base font-semibold text-[color:var(--color-site-ink)] transition hover:bg-white/80 hover:text-[color:var(--color-site-brand-text)]"
                            target="{{ $itemTarget }}"
                            @if($isActive) aria-current="page" @endif
                            @if($itemTarget === '_blank') rel="noopener noreferrer" @endif
                        >{{ $item['label'] ?? 'Untitled' }}</a>
                    @endif
                @endforeach
                @guest
                    <a href="/download" class="ui-btn-primary motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none !mt-1 !w-full !justify-center !py-3 !text-base">
                        Get Started
                        <i class="fa-solid fa-arrow-right-long" aria-hidden="true"></i>
                    </a>
                @endguest
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            @if($searchIconToggleEnabled ?? false)
                <button type="button" class="inline-flex cursor-pointer items-center justify-center rounded-full border border-[var(--color-site-line)] bg-[color:var(--color-site-panel)] p-3 text-[color:var(--color-site-ink)] transition hover:border-[color:var(--color-site-brand-text)] hover:text-[color:var(--color-site-brand-text)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--color-site-brand)]" aria-label="Toggle search" aria-expanded="false" aria-controls="desktop-search-panel" data-search-toggle-trigger>
                    <span class="sr-only">Toggle search</span>
                    <span class="flex h-5 w-5 items-center justify-center" aria-hidden="true">
                        <i class="fa fa-search"></i>
                    </span>
                </button>
            @endif
            @if(auth()->check())
                @php
                    /** @var \App\Models\User $authUser */
                    $authUser = auth()->user();
                    $avatarInitials = strtoupper((string) ($authUser?->initials() ?: substr((string) $authUser?->username, 0, 2)));
                    $unreadPmCount = (int) ($unreadPrivateMessageCount ?? 0);
                    $unreadPmBadge = $unreadPmCount > 99 ? '99+' : (string) $unreadPmCount;
                    $unreadNotifCount = (int) ($unreadNotificationCount ?? 0);
                    $unreadNotifBadge = $unreadNotifCount > 99 ? '99+' : (string) $unreadNotifCount;
                    $bellItems = collect($recentNotifications ?? []);
                @endphp
                <details class="relative" data-notif-menu data-user-menu>
                    <summary class="relative inline-flex cursor-pointer list-none items-center justify-center rounded-full border border-[var(--color-site-line)] bg-[color:var(--color-site-panel)] p-3 text-[color:var(--color-site-ink)] transition hover:border-[color:var(--color-site-brand-text)] hover:text-[color:var(--color-site-brand-text)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f] [&::-webkit-details-marker]:hidden" aria-label="{{ $unreadNotifCount > 0 ? __('notifications.bell.unread_badge_label', ['count' => $unreadNotifBadge]) : __('notifications.bell.open') }}" title="Notifications">
                        <span class="flex h-5 w-5 items-center justify-center" aria-hidden="true">
                            <i class="fa-regular fa-bell"></i>
                        </span>
                        @if($unreadNotifCount > 0)
                            <span aria-hidden="true" class="pointer-events-none absolute -top-1 -right-1 inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full border-2 border-[color:var(--color-site-panel)] bg-[#933c1f] px-1 text-[11px] font-bold leading-none text-white">{{ $unreadNotifBadge }}</span>
                        @endif
                    </summary>
                    <div class="fixed inset-x-3 top-[88px] z-[100000] pt-0 sm:absolute sm:right-0 sm:top-full sm:left-auto sm:w-[360px] sm:pt-[10px]">
                        <div class="grid max-h-[calc(100vh-108px)] origin-top-right gap-2 overflow-y-auto rounded-[14px] border border-[#d6c7b3] bg-[#ececed] p-3 shadow-[0_16px_34px_rgba(32,20,10,0.16),0_2px_8px_rgba(32,20,10,0.1)] sm:max-h-none sm:overflow-visible sm:before:absolute sm:before:-top-[7px] sm:before:right-5 sm:before:h-3 sm:before:w-3 sm:before:rotate-45 sm:before:border-l sm:before:border-t sm:before:border-[#d6c7b3] sm:before:bg-[#ececed] sm:before:content-[''] motion-safe:animate-[home-fade-in-up_.2s_ease-out_both] motion-reduce:animate-none" aria-label="{{ __('notifications.bell.label') }}">
                            <div class="flex items-center justify-between gap-2 px-1">
                                <strong class="text-[15px] leading-[1.2]">{{ __('notifications.bell.label') }}</strong>
                                <div class="flex items-center gap-3">
                                    @if($unreadNotifCount > 0)
                                        <form method="post" action="{{ route('notifications.read-all') }}">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-[#8f3f1f] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ __('notifications.bell.mark_all_read') }}</button>
                                        </form>
                                    @endif
                                    @if($bellItems->isNotEmpty())
                                        <form method="post" action="{{ route('notifications.clear-all') }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('notifications.bell.clear_all_confirm')) }})">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-[#8f3f1f] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ __('notifications.bell.clear_all') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            <a href="{{ route('notifications.index') }}" class="border-b border-[#d9d0c4] px-1 pb-2 text-xs font-semibold text-[#8f3f1f] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ __('notifications.bell.view_all') }}</a>
                            @forelse($bellItems as $bellItem)
                                @php $bellData = is_array($bellItem->data) ? $bellItem->data : []; @endphp
                                <a
                                    href="{{ route('notifications.open', $bellItem->id) }}"
                                    rel="nofollow"
                                    class="grid min-w-0 gap-0.5 rounded-[10px] border border-transparent px-2 py-2 transition hover:border-[#d7b8a6] hover:bg-[#f7e8df] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f] {{ $bellItem->read_at === null ? 'bg-white' : '' }}"
                                >
                                    <span class="flex min-w-0 items-start gap-2">
                                        @if($bellItem->read_at === null)
                                            <span class="mt-1.5 inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-[#933c1f]" aria-hidden="true"></span>
                                        @else
                                            <span class="mt-1.5 inline-block h-1.5 w-1.5 shrink-0" aria-hidden="true"></span>
                                        @endif
                                        <span class="min-w-0 flex-1">
                                            <span class="flex min-w-0 items-center gap-1.5">
                                                <span class="block min-w-0 truncate text-sm font-semibold text-[#0f2133]">{{ $bellData['title'] ?? __('notifications.bell.label') }}</span>
                                                @if(($bellData['count'] ?? 1) > 1)
                                                    <span class="shrink-0 rounded-pill bg-[#e6e0d6] px-1.5 py-0.5 text-[10px] font-bold text-[#5c6570]">{{ $bellData['count'] }}</span>
                                                @endif
                                            </span>
                                            @if(!empty($bellData['body']))
                                                <span class="block truncate text-xs text-[#5c6570]">{{ $bellData['body'] }}</span>
                                            @endif
                                            <span class="block text-[11px] text-[#8a94a0]">{{ $bellItem->created_at?->diffForHumans() }}</span>
                                        </span>
                                    </span>
                                </a>
                            @empty
                                <p class="px-2 py-3 text-sm text-[#5c6570]">{{ __('notifications.bell.empty') }}</p>
                            @endforelse
                        </div>
                    </div>
                </details>
                <details class="relative" data-user-menu>
                    <summary class="relative inline-flex cursor-pointer list-none items-center rounded-full border border-[var(--color-site-line)] bg-[color:var(--color-site-panel)] p-1 transition hover:border-[color:var(--color-site-brand-text)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f] [&::-webkit-details-marker]:hidden" aria-label="Open user menu{{ $unreadPmCount > 0 ? ', '.$unreadPmBadge.' unread messages' : '' }}" title="Account menu">
                        <span class="inline-flex h-[38px] w-[38px] items-center justify-center overflow-hidden rounded-full border border-[#c97746] bg-[radial-gradient(circle_at_25%_20%,#ffd7b6_0%,transparent_42%),linear-gradient(145deg,#fef2e6_0%,#ffdcbf_46%,#ffc897_100%)] text-[12px] font-bold tracking-[0.04em] text-[#9f4413] shadow-[inset_0_1px_0_rgba(255,255,255,0.75),0_1px_4px_rgba(55,30,10,0.16)]">
                            <img src="{{ $authUser?->avatarUrl(80) }}" alt="{{ $avatarInitials }}" class="block h-full w-full object-cover">
                        </span>
                        @if($unreadPmCount > 0)
                            <span aria-hidden="true" class="pointer-events-none absolute -top-1 -right-1 inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full border-2 border-[color:var(--color-site-panel)] bg-[#933c1f] px-1 text-[11px] font-bold leading-none text-white">{{ $unreadPmBadge }}</span>
                        @endif
                    </summary>
                    <div class="fixed inset-x-3 top-[88px] z-[100000] pt-0 sm:absolute sm:right-0 sm:top-full sm:left-auto sm:w-[320px] sm:pt-[10px]">
                        <div class="grid max-h-[calc(100vh-108px)] origin-top-right gap-2.5 overflow-y-auto rounded-[14px] border border-[#d6c7b3] bg-[#ececed] p-3 shadow-[0_16px_34px_rgba(32,20,10,0.16),0_2px_8px_rgba(32,20,10,0.1)] sm:max-h-none sm:overflow-visible sm:before:absolute sm:before:-top-[7px] sm:before:right-5 sm:before:h-3 sm:before:w-3 sm:before:rotate-45 sm:before:border-l sm:before:border-t sm:before:border-[#d6c7b3] sm:before:bg-[#ececed] sm:before:content-[''] motion-safe:animate-[home-fade-in-up_.2s_ease-out_both] motion-reduce:animate-none" aria-label="User menu">
                            <div class="grid gap-1 border-b border-[#d9d0c4] px-2 pb-2.5 pt-1">
                                <strong class="text-[15px] leading-[1.2]">{{ $authUser?->username ?: $authUser?->name ?: 'User' }}</strong>
                                <span class="truncate text-xs text-[#5c6570]">{{ $authUser?->email }}</span>
                            </div>
                            @php
                                $messagesPath = rtrim(parse_url(route('messages.index'), PHP_URL_PATH) ?? '/messages', '/');
                                $hasMessagesMenuItem = collect($userMenuItems ?? [])
                                    ->contains(fn ($menuItem) => is_string($menuItem['url'] ?? null)
                                        && rtrim(parse_url($menuItem['url'], PHP_URL_PATH) ?? '', '/') === $messagesPath);
                            @endphp
                            @unless($hasMessagesMenuItem)
                                <a href="{{ route('messages.index') }}" class="flex w-full items-center justify-between gap-2 rounded-[10px] border border-transparent px-3 py-2.5 text-left font-semibold text-[#0f2133] transition hover:border-[#d7b8a6] hover:bg-[#f7e8df] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">
                                    <span>Messages</span>
                                    @if($unreadPmCount > 0)
                                        <span class="inline-flex min-w-[20px] items-center justify-center rounded-full bg-[#933c1f] px-1.5 py-0.5 text-xs font-bold text-white" aria-label="{{ $unreadPmCount }} unread messages">{{ $unreadPmBadge }}</span>
                                    @endif
                                </a>
                            @endunless
                            @foreach(($userMenuItems ?? []) as $item)
                                @php
                                    $itemType = is_string($item['type'] ?? null) ? $item['type'] : 'url';
                                    $itemLabel = is_string($item['label'] ?? null) ? $item['label'] : 'Untitled';
                                    $itemUrl = is_string($item['url'] ?? null) ? $item['url'] : '#';
                                    $itemTarget = $itemType === 'url' && is_string($item['target'] ?? null) ? $item['target'] : '_self';
                                    $itemReference = is_string($item['reference'] ?? null) ? $item['reference'] : '';
                                    $itemIsMessages = $itemType !== 'action'
                                        && rtrim(parse_url($itemUrl, PHP_URL_PATH) ?? '', '/') === $messagesPath;
                                @endphp

                                @if($itemType === 'action' && $itemReference === 'logout')
                                    <form method="post" action="{{ route('frontend.logout') }}">
                                        @csrf
                                        <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                                        <button type="submit" class="block w-full cursor-pointer rounded-[10px] border border-transparent px-3 py-2.5 text-left font-semibold text-[#0f2133] transition hover:border-[#d7b8a6] hover:bg-[#f7e8df] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ $itemLabel }}</button>
                                    </form>
                                @elseif($itemIsMessages)
                                    <a href="{{ $itemUrl }}" class="flex w-full items-center justify-between gap-2 rounded-[10px] border border-transparent px-3 py-2.5 text-left font-semibold text-[#0f2133] transition hover:border-[#d7b8a6] hover:bg-[#f7e8df] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                                       @if($itemTarget !== '_self') target="{{ $itemTarget }}" @endif
                                       @if($itemTarget === '_blank') rel="noopener noreferrer" @endif
                                    >
                                        <span>{{ $itemLabel }}</span>
                                        @if($unreadPmCount > 0)
                                            <span class="inline-flex min-w-[20px] items-center justify-center rounded-full bg-[#933c1f] px-1.5 py-0.5 text-xs font-bold text-white" aria-label="{{ $unreadPmCount }} unread messages">{{ $unreadPmBadge }}</span>
                                        @endif
                                    </a>
                                @else
                                    <a href="{{ $itemUrl }}" class="block w-full rounded-[10px] border border-transparent px-3 py-2.5 text-left font-semibold text-[#0f2133] transition hover:border-[#d7b8a6] hover:bg-[#f7e8df] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                                       @if($itemTarget !== '_self') target="{{ $itemTarget }}" @endif
                                       @if($itemTarget === '_blank') rel="noopener noreferrer" @endif
                                    >{{ $itemLabel }}</a>
                                @endif
                            @endforeach
                            @if($staffFrontendPreviewAvailable ?? false)
                                <form method="post" action="{{ route('profile.staff-frontend-preview.update') }}">
                                    @csrf
                                    <input type="hidden" name="enabled" value="{{ ($staffFrontendPreviewEnabled ?? false) ? '0' : '1' }}">
                                    <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                                    <button type="submit" class="block w-full cursor-pointer rounded-[10px] border border-transparent px-3 py-2.5 text-left font-semibold text-[#0f2133] transition hover:border-[#d7b8a6] hover:bg-[#f7e8df] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">
                                        {{ ($staffFrontendPreviewEnabled ?? false) ? 'Show Moderation Controls: On' : 'Show Moderation Controls: Off' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </details>
            @else
                <a href="/download" class="ui-btn-primary ui-btn-swipe hidden !px-6 !py-3 !text-sm min-[880px]:inline-flex min-[880px]:items-center min-[880px]:gap-3">
                    Get Started
                    <span aria-hidden="true">→</span>
                </a>
            @endif
        </div>
        </div>
    </div>

    <div
        @if($searchIconToggleEnabled ?? false)
            id="desktop-search-panel" class="border-t border-[var(--color-site-line)]" hidden data-search-toggle-panel
        @else
            class="hidden border-t border-[var(--color-site-line)] md:block"
        @endif
    >
        <div class="mx-auto flex max-w-[1600px] items-center justify-end gap-6 px-8 py-2 xl:px-10">
            <form method="get" action="{{ route('search.index') }}" class="flex w-full max-w-[540px] items-center gap-3">
                <label for="desktop-global-search" class="sr-only">Search site</label>
                <div class="flex flex-1 items-center gap-2 rounded-full border border-[var(--color-site-line)] bg-white px-4 py-1.5 shadow-sm">
                    <input id="desktop-global-search" type="search" name="q" value="{{ is_scalar(request('q')) ? request('q') : '' }}" placeholder="Search docs, plugins, and discussions..." class="w-full bg-transparent text-sm text-[color:var(--color-site-ink)] outline-none placeholder:text-[color:var(--color-site-muted)]">
                    <label for="desktop-global-search-type" class="sr-only">Search type</label>
                    <select id="desktop-global-search-type" name="type" class="shrink-0 border-l border-[var(--color-site-line)] bg-transparent pl-2 text-sm text-[color:var(--color-site-muted)] outline-none">
                        <option value="">All</option>
                        @foreach(\App\SearchResultType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="inline-flex cursor-pointer items-center gap-1.5 text-sm font-semibold text-[color:var(--color-site-brand-text)] transition hover:text-[color:var(--color-site-brand-dark)]">
                        <i class="fa fa-search" aria-hidden="true"></i>
                        <span>Search</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</header>

@include('partials.site_notification_banners')

@if(($staffImpersonationActive ?? false) && auth()->check())
    <div class="border-b border-[#d7b8a6] bg-[#f7e8df]">
        <div class="mx-auto flex max-w-[1600px] flex-wrap items-center justify-between gap-3 px-6 py-3 text-sm text-[#6f2f16] md:px-10">
            <p>
                Acting as <strong>{{ auth()->user()?->username ?: auth()->user()?->name ?: 'user' }}</strong>
                @if(!empty($staffImpersonationOriginalUsername))
                    for <strong>{{ $staffImpersonationOriginalUsername }}</strong>.
                @endif
            </p>
            <form method="post" action="{{ route('staff.impersonation.stop') }}">
                @csrf
                <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                <button type="submit" class="inline-flex cursor-pointer items-center rounded-full border border-[#8f3f1f] bg-[#8f3f1f] px-4 py-2 font-semibold text-white transition hover:bg-[#6f2f16] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">
                    Return to staff account
                </button>
            </form>
        </div>
    </div>
@endif

@php
    // login/register share field names ("username", "password"), so which
    // form actually failed can't be told apart from $errors alone - this
    // hidden marker (see auth.partials.login_form/register_form) survives
    // the redirect-back via Laravel's normal old-input flashing and names
    // the form directly, and doubles as the key into nativeAuthPanels in
    // site-header.js.
    $reopenAuthForm = old('_auth_form');
    $reopenAuthForm = in_array($reopenAuthForm, ['login', 'register'], true) && ! request()->routeIs($reopenAuthForm)
        ? $reopenAuthForm
        : null;
@endphp
<div
    class="fixed inset-0 z-[1200] flex items-center justify-center p-4"
    id="home-auth-modal"
    @if ($reopenAuthForm !== null) data-auth-modal-open-on-load="{{ $reopenAuthForm }}" @endif
    hidden
>
    <div class="absolute inset-0 bg-[rgba(12,10,8,0.58)] backdrop-blur-[2px]" data-auth-modal-close></div>
    <div class="relative z-[1] max-h-[calc(100vh-2rem)] w-full max-w-lg overflow-y-auto rounded-surface border border-[#d8dce2] bg-white p-6 shadow-[0_30px_60px_rgba(0,0,0,0.32)]" id="home-auth-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="home-auth-modal-title" tabindex="-1">
        <h2 id="home-auth-modal-title" class="sr-only">Authentication</h2>
        <button type="button" class="absolute right-3 top-3 inline-flex h-8 w-8 items-center justify-center rounded-full text-lg leading-none text-[#5c6570] transition hover:bg-[#f4f1ea] hover:text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-auth-modal-close data-auth-modal-close-btn aria-label="Close authentication modal">×</button>

        <div class="mb-5 flex justify-center">
            <img src="{{ asset('assets/images/logo/zen-cart-logo.png') }}" alt="Zen Cart" width="254" height="68" class="h-10 w-auto">
        </div>

        {{--
            Login and register are native (plain forms, submitted and
            validated server-side).

            Each is skipped entirely on its own standalone page: that page
            already renders the same partial as its real content, and
            site_header (this partial) is included there too - without this
            guard the form would exist twice in the DOM (duplicate ids/names,
            two Turnstile widgets fighting over one hidden input, etc).
        --}}
        @unless (request()->routeIs('login'))
            <div id="home-auth-modal-native-login" hidden>
                <h3 class="mb-4 text-center text-lg font-semibold text-[#0f2133]">{{ $pendingTwoFactorLoginUser ? 'Two-Factor Verification' : 'Log In' }}</h3>
                @include('auth.partials.login_form', ['pendingUser' => $pendingTwoFactorLoginUser])
            </div>
        @endunless
        @unless (request()->routeIs('register'))
            <div id="home-auth-modal-native-register" hidden>
                <h3 class="mb-4 text-center text-lg font-semibold text-[#0f2133]">Create an Account</h3>
                @include('auth.partials.register_form', ['turnstileEnabled' => $registerTurnstileEnabled])
            </div>
        @endunless
        @unless (request()->routeIs('password.request'))
            <div id="home-auth-modal-native-password-request" hidden>
                <h3 class="mb-4 text-center text-lg font-semibold text-[#0f2133]">Reset Password</h3>
                @include('auth.partials.password_request_form')
            </div>
        @endunless
    </div>
</div>
