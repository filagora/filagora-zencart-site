@extends('layouts.main')

@section('title')
{{ __('notifications.bell.label') }}
@endsection

@section('main_class', 'bg-white')

@section('content')
    <div class="mx-auto max-w-[900px] px-6 py-8 md:px-10">
        @include('partials.site_guest_intro')

        <section class="mt-[18px] grid gap-3">
            <header class="flex items-center justify-between gap-2.5">
                <div>
                    <h1 class="ui-page-title">{{ __('notifications.bell.label') }}</h1>
                    <p class="ui-page-subtitle mt-2">Replies, mentions, plugin updates and moderation decisions land here.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($unreadCount > 0)
                        <form method="post" action="{{ route('notifications.read-all') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-surface border border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ __('notifications.bell.mark_all_read') }}</button>
                        </form>
                    @endif
                    @if($notifications->total() > 0)
                        <form method="post" action="{{ route('notifications.clear-read') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-surface border border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ __('notifications.bell.clear_read') }}</button>
                        </form>
                    @endif
                    @if($notifications->total() > 0)
                        <form method="post" action="{{ route('notifications.clear-all') }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('notifications.bell.clear_all_confirm')) }})">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-surface border border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ __('notifications.bell.clear_all') }}</button>
                        </form>
                    @endif
                </div>
            </header>

            @if(session('profile_status'))
                <div class="rounded-surface border border-[#b9d6bd] bg-[#f1fbf2] px-3 py-2 text-sm text-[#1f5130]" role="status" aria-live="polite">{{ session('profile_status') }}</div>
            @endif

            <div class="flex gap-2 text-sm font-semibold" role="tablist" aria-label="{{ __('notifications.bell.label') }}">
                <a href="{{ route('notifications.index') }}" aria-current="{{ $onlyUnread ? 'false' : 'true' }}" class="rounded-pill px-3 py-1.5 transition {{ $onlyUnread ? 'text-[#5c6570] hover:text-[#8f3f1f]' : 'bg-[#8f3f1f] text-white' }}">{{ __('notifications.bell.filter_all') }}</a>
                <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" aria-current="{{ $onlyUnread ? 'true' : 'false' }}" class="rounded-pill px-3 py-1.5 transition {{ $onlyUnread ? 'bg-[#8f3f1f] text-white' : 'text-[#5c6570] hover:text-[#8f3f1f]' }}">{{ __('notifications.bell.filter_unread') }}</a>
            </div>

            <div class="divide-y divide-[#e6e0d6] overflow-hidden rounded-surface border border-[#d8dce2] bg-white">
                @forelse($notifications as $notification)
                    @php $data = is_array($notification->data) ? $notification->data : []; @endphp
                    <div class="flex items-stretch {{ $notification->read_at === null ? 'bg-[#fbf5ef]' : '' }}">
                        <a
                            href="{{ route('notifications.open', $notification->id) }}"
                            rel="nofollow"
                            class="flex min-w-0 flex-1 items-start gap-3 px-4 py-3 transition hover:bg-[#f7e8df] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                        >
                            <span class="mt-1.5 inline-block h-2 w-2 shrink-0 rounded-full {{ $notification->read_at === null ? 'bg-[#933c1f]' : 'bg-transparent' }}" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-1.5">
                                    <span class="block text-sm font-semibold text-[#0f2133]">{{ $data['title'] ?? __('notifications.bell.label') }}</span>
                                    @if(($data['count'] ?? 1) > 1)
                                        <span class="shrink-0 rounded-pill bg-[#e6e0d6] px-1.5 py-0.5 text-[10px] font-bold text-[#5c6570]">{{ $data['count'] }}</span>
                                    @endif
                                </span>
                                @if(!empty($data['body']))
                                    <span class="mt-0.5 block text-sm text-[#5c6570]">{{ $data['body'] }}</span>
                                @endif
                                <span class="mt-1 block text-xs text-[#8a94a0]">{{ $notification->created_at?->diffForHumans() }}</span>
                            </span>
                        </a>
                        <form method="post" action="{{ route('notifications.destroy', $notification->id) }}" class="flex shrink-0 items-start p-2">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-control text-[#8a94a0] transition hover:bg-[#f2ede5] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" aria-label="{{ __('notifications.bell.remove') }}">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-[#5c6570]">{{ __($onlyUnread ? 'notifications.bell.empty_unread' : 'notifications.bell.empty') }}</p>
                @endforelse
            </div>

            {{ $notifications->links() }}
        </section>
    </div>
@endsection
