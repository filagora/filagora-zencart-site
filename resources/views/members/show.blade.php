@extends('layouts.main')

@section('title', 'Member')
@section('main_class', 'bg-white')

@section('content')
    @php
        $displayName = $member->username ?: $member->name ?: 'User #'.$member->id;
        $pmUsername = trim((string) ($member->username ?? ''));
        $canSendPm = auth()->check()
            && (int) auth()->id() !== (int) $member->id
            && $pmUsername !== ''
            && $member->allowsPrivateMessages()
            && !$viewerHasIgnoredMember
            && !$memberHasIgnoredViewer;
        $activeTab = in_array(request('tab'), ['plugins', 'releases', 'threads', 'posts'], true)
            ? request('tab')
            : 'overview';
        $tabClass = 'inline-flex items-center rounded-pill border border-[#cabca8] bg-white px-3 py-1.5 text-sm font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f]';
        $activeTabClass = 'inline-flex items-center rounded-pill border border-[#8f3f1f] bg-[#f7e8df] px-3 py-1.5 text-sm font-semibold text-[#8f3f1f]';
        $memberUrl = route('members.show', $member);
        $joinedAt = $member->joined_at;
        $lastActivity = $latestSessionActivity ? \Illuminate\Support\Carbon::createFromTimestamp($latestSessionActivity) : null;
        $daysSinceJoin = $joinedAt?->diffInDays(now()) ?? 0;
        $postsPerDay = $visiblePostCount > 0
            ? $visiblePostCount / max(1, $daysSinceJoin)
            : 0;
        $basicInfo = array_filter([
            'Username' => $member->username,
            'Real Name' => $member->name,
            'User Title' => $member->user_title,
            'Occupation' => $member->occupation,
            'Interests' => $member->interests,
        ], fn ($value) => filled($value));
        $basicInfo = ['Location' => filled($member->location) ? $member->location : 'Not specified'] + $basicInfo;
    @endphp
    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
        @if(session('members_status'))
            <div class="mb-4 border border-[#d7b8a6] bg-[#f7e8df] px-4 py-3 text-sm text-[#6f2f16]">
                {{ session('members_status') }}
            </div>
        @endif
        <header class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-4">
                <img
                    src="{{ $member->avatarUrl(160) }}"
                    alt="{{ $displayName }} avatar"
                    class="h-20 w-20 rounded-pill border border-[#cabca8] object-cover shadow-[0_2px_6px_rgba(21,16,11,0.08)]"
                >
                <div>
                    <a
                        href="{{ route('members.index') }}"
                        class="inline-flex items-center text-sm font-semibold text-[#8f3f1f] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                    >Back to Members Overview</a>
                    <h1 class="mt-2 text-3xl font-semibold text-[#0f2133]">Member Profile</h1>
                    <p class="mt-1 text-sm text-[#5c6570]">Contributions and release history for {{ $displayName }}.</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($viewerCanEmailMember && filled($member->email))
                    <a
                        href="mailto:{{ $member->email }}"
                        class="inline-flex items-center rounded-pill border border-[#cabca8] bg-white px-3.5 py-2 text-sm font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                    >Email</a>
                @endif

                @if($canSendPm)
                    <a
                        href="{{ route('messages.compose', ['to' => $pmUsername]) }}"
                        class="inline-flex items-center rounded-pill border border-[#933c1f] bg-[#933c1f] px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                    >Message</a>
                @endif

                @if((int) auth()->id() !== (int) $member->id)
                    @if($viewerHasIgnoredMember)
                        <form method="post" action="{{ route('members.ignore.destroy', $member) }}">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="return_tab" value="{{ $activeTab }}">
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-pill border border-[#cabca8] bg-white px-3.5 py-2 text-sm font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                            >Remove from ignore list</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('members.ignore.store', $member) }}">
                            @csrf
                            <input type="hidden" name="return_tab" value="{{ $activeTab }}">
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-pill border border-[#cabca8] bg-white px-3.5 py-2 text-sm font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                            >Add to ignore list</button>
                        </form>
                    @endif
                @endif
            </div>
        </header>

        <nav class="mb-4 flex flex-wrap gap-2" aria-label="Profile tabs">
            <a href="{{ route('members.show', ['user' => $member->id, 'tab' => 'overview']) }}" class="{{ $activeTab === 'overview' ? $activeTabClass : $tabClass }}">Overview</a>
            <a href="{{ route('members.show', ['user' => $member->id, 'tab' => 'plugins']) }}" class="{{ $activeTab === 'plugins' ? $activeTabClass : $tabClass }}">Plugins</a>
            <a href="{{ route('members.show', ['user' => $member->id, 'tab' => 'releases']) }}" class="{{ $activeTab === 'releases' ? $activeTabClass : $tabClass }}">Releases</a>
            <a href="{{ route('members.show', ['user' => $member->id, 'tab' => 'threads']) }}" class="{{ $activeTab === 'threads' ? $activeTabClass : $tabClass }}">Threads</a>
            <a href="{{ route('members.show', ['user' => $member->id, 'tab' => 'posts']) }}" class="{{ $activeTab === 'posts' ? $activeTabClass : $tabClass }}">Posts</a>
        </nav>

        @if($activeTab === 'overview')
            <section class="grid gap-3">
                <article class="overflow-hidden border border-[#d8dce2] bg-white">
                    <div class="border-b border-[#d8dce2] bg-white px-4 py-3">
                        <h2 class="text-xl font-semibold text-[#243342]">Basic Information</h2>
                    </div>
                    <div class="grid gap-4 px-4 py-4 md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
                        <div>
                            <h3 class="text-lg font-semibold text-[#0f2133]">About '{{ $displayName }}'</h3>
                            @if(filled($member->biography))
                                <div class="mt-2 whitespace-pre-line text-sm leading-6 text-[#304458]">{{ $member->biography }}</div>
                            @else
                                <p class="mt-2 text-sm text-[#5c6570]">This member has not added an about me description yet.</p>
                            @endif
                        </div>
                        <dl class="grid gap-2 border border-[#d8dce2] bg-white p-4">
                            @forelse($basicInfo as $label => $value)
                                <div class="grid gap-0.5 sm:grid-cols-[140px_minmax(0,1fr)] sm:items-start">
                                    <dt class="text-sm font-bold text-[#304458]">{{ $label }}:</dt>
                                    <dd class="text-sm text-[#304458]">{{ $value }}</dd>
                                </div>
                            @empty
                                <div class="text-sm text-[#5c6570]">No additional profile details have been shared.</div>
                            @endforelse
                        </dl>
                    </div>
                </article>

                <article class="overflow-hidden border border-[#d8dce2] bg-white">
                    <div class="border-b border-[#d8dce2] bg-white px-4 py-3">
                        <h2 class="text-xl font-semibold text-[#243342]">Contact</h2>
                    </div>
                    <div class="px-4 py-4">
                        <div class="grid gap-0.5 sm:grid-cols-[140px_minmax(0,1fr)] sm:items-start">
                            <dt class="text-sm font-bold text-[#304458]">This Page:</dt>
                            <dd class="text-sm text-[#304458] break-all">
                                <a href="{{ $memberUrl }}" class="text-[#0d5fc8] hover:underline">{{ $memberUrl }}</a>
                            </dd>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a
                                href="{{ route('members.posts', $member) }}"
                                class="inline-flex items-center rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#304458] hover:border-[#8f3f1f] hover:text-[#8f3f1f]"
                            >Find latest posts</a>
                            <a
                                href="{{ route('members.threads', $member) }}"
                                class="inline-flex items-center rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#304458] hover:border-[#8f3f1f] hover:text-[#8f3f1f]"
                            >Find latest started threads</a>
                        </div>
                    </div>
                </article>

                <article class="overflow-hidden border border-[#d8dce2] bg-white">
                    <div class="border-b border-[#d8dce2] bg-white px-4 py-3">
                        <h2 class="text-xl font-semibold text-[#243342]">Statistics</h2>
                    </div>
                    <div class="grid gap-4 px-4 py-4 md:grid-cols-2">
                        <div class="border border-[#d8dce2] bg-white p-4">
                            <h3 class="text-lg font-semibold text-[#0f2133]">Forum Activity</h3>
                            <dl class="mt-3 grid gap-2">
                                <div class="grid gap-0.5 sm:grid-cols-[140px_minmax(0,1fr)]">
                                    <dt class="text-sm font-bold text-[#304458]">Total Posts:</dt>
                                    <dd class="text-sm text-[#304458]">{{ number_format($visiblePostCount) }}</dd>
                                </div>
                                <div class="grid gap-0.5 sm:grid-cols-[140px_minmax(0,1fr)]">
                                    <dt class="text-sm font-bold text-[#304458]">Posts Per Day:</dt>
                                    <dd class="text-sm text-[#304458]">{{ number_format($postsPerDay, 2) }}</dd>
                                </div>
                                <div class="grid gap-0.5 sm:grid-cols-[140px_minmax(0,1fr)]">
                                    <dt class="text-sm font-bold text-[#304458]">Join Date:</dt>
                                    <dd class="text-sm text-[#304458]">{{ $joinedAt ? \App\Support\LocalTime::format($joinedAt, 'date') : 'Unknown' }}</dd>
                                </div>
                                <div class="grid gap-0.5 sm:grid-cols-[140px_minmax(0,1fr)]">
                                    <dt class="text-sm font-bold text-[#304458]">Last Activity:</dt>
                                    <dd class="text-sm text-[#304458]">{{ $lastActivity ? \App\Support\LocalTime::format($lastActivity) : 'No recent session activity recorded' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="border border-[#d8dce2] bg-white p-4">
                            <h3 class="text-lg font-semibold text-[#0f2133]">Contributions</h3>
                            <dl class="mt-3 grid gap-2">
                                <div class="grid gap-0.5 sm:grid-cols-[160px_minmax(0,1fr)]">
                                    <dt class="text-sm font-bold text-[#304458]">Plugin Contributions:</dt>
                                    <dd class="text-sm text-[#304458]">{{ number_format($plugins->total()) }}</dd>
                                </div>
                                <div class="grid gap-0.5 sm:grid-cols-[160px_minmax(0,1fr)]">
                                    <dt class="text-sm font-bold text-[#304458]">Uploads:</dt>
                                    <dd class="text-sm text-[#304458]">{{ number_format($releases->total()) }}</dd>
                                </div>
                            </dl>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a class="inline-flex items-center rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#304458] hover:border-[#8f3f1f] hover:text-[#8f3f1f]" href="{{ route('members.show', ['user' => $member->id, 'tab' => 'plugins']) }}">
                                    View plugins
                                </a>
                                <a class="inline-flex items-center rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#304458] hover:border-[#8f3f1f] hover:text-[#8f3f1f]" href="{{ route('members.show', ['user' => $member->id, 'tab' => 'releases']) }}">
                                    View releases
                                </a>
                            </div>
                        </div>
                    </div>
                </article>
            </section>
        @endif

        @if($activeTab === 'plugins')
            <section class="border border-[#d8dce2] bg-white p-4">
                <h2 class="text-xl font-semibold text-[#0f2133]">Plugins Authored</h2>
                @if(!$canViewNonPublicContributions)
                    <p class="mt-1 text-xs text-[#5c6570]">Showing publicly visible contributions only.</p>
                @endif

                @if($plugins->count() === 0)
                    <p class="mt-3 text-sm text-[#5c6570]">No plugins found.</p>
                @else
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full border-collapse">
                            <thead class="bg-[#f2f5fa]">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-bold uppercase tracking-[0.07em] text-[#5c6570]">Plugin</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold uppercase tracking-[0.07em] text-[#5c6570]">Type</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold uppercase tracking-[0.07em] text-[#5c6570]">Status</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold uppercase tracking-[0.07em] text-[#5c6570]">Latest Release</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold uppercase tracking-[0.07em] text-[#5c6570]">Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($plugins as $plugin)
                                    @php
                                        $status = $plugin->status?->label() ?? ucfirst((string) $plugin->status);
                                        $canLinkPlugin = ($plugin->status?->value ?? null) === \Filagora\Plugins\Enums\PluginStatus::ACTIVE->value
                                            || $canViewNonPublicContributions;
                                    @endphp
                                    <tr class="border-t border-[#e0d7cc]">
                                        <td class="px-3 py-2 text-sm text-[#304458]">
                                            @if($canLinkPlugin)
                                                <a class="font-semibold text-[#0d5fc8] hover:underline" href="{{ route('plugins.show', $plugin) }}">{{ $plugin->name }}</a>
                                            @else
                                                <span class="font-semibold">{{ $plugin->name }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-sm text-[#304458]">{{ $plugin->type?->name ?? 'Unknown' }}</td>
                                        <td class="px-3 py-2 text-sm text-[#304458]">{{ $status }}</td>
                                        <td class="px-3 py-2 text-sm text-[#304458]">
                                            @if($plugin->latest_release_version)
                                                {{ $plugin->latest_release_version }}
                                                @if($plugin->latest_release_created_at)
                                                    <span class="text-xs text-[#5c6570]">({{ \App\Support\LocalTime::format(\Illuminate\Support\Carbon::parse($plugin->latest_release_created_at), 'date') }})</span>
                                                @endif
                                            @else
                                                None
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-sm text-[#304458]">{{ \App\Support\LocalTime::format($plugin->updated_at, 'date') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $plugins->appends(['tab' => 'plugins', 'releases_page' => request('releases_page'), 'threads_page' => request('threads_page'), 'posts_page' => request('posts_page')])->links() }}
                    </div>
                @endif
            </section>
        @endif

        @if($activeTab === 'releases')
            <section class="border border-[#d8dce2] bg-white p-4">
                <h2 class="text-xl font-semibold text-[#0f2133]">Uploaded Releases</h2>
                @if(!$canViewNonPublicContributions)
                    <p class="mt-1 text-xs text-[#5c6570]">Showing publicly visible contributions only.</p>
                @endif

                @if($releases->count() === 0)
                    <p class="mt-3 text-sm text-[#5c6570]">No releases found.</p>
                @else
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full border-collapse">
                            <thead class="bg-[#f2f5fa]">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-bold uppercase tracking-[0.07em] text-[#5c6570]">Version</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold uppercase tracking-[0.07em] text-[#5c6570]">Plugin</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold uppercase tracking-[0.07em] text-[#5c6570]">Status</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold uppercase tracking-[0.07em] text-[#5c6570]">Encapsulated</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold uppercase tracking-[0.07em] text-[#5c6570]">ZC Versions</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold uppercase tracking-[0.07em] text-[#5c6570]">Uploaded</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($releases as $release)
                                    @php
                                        $plugin = $release->plugin;
                                        $canLinkPlugin = $plugin && (
                                            ($plugin->status?->value ?? null) === \Filagora\Plugins\Enums\PluginStatus::ACTIVE->value
                                            || $canViewNonPublicContributions
                                        );
                                    @endphp
                                    <tr class="border-t border-[#e0d7cc]">
                                        <td class="px-3 py-2 text-sm font-semibold text-[#304458]">{{ $release->version }}</td>
                                        <td class="px-3 py-2 text-sm text-[#304458]">
                                            @if($plugin && $canLinkPlugin)
                                                <a class="font-semibold text-[#0d5fc8] hover:underline" href="{{ route('plugins.show', $plugin) }}">{{ $plugin->name }}</a>
                                            @else
                                                {{ $plugin?->name ?? 'Unknown plugin' }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-sm text-[#304458]">{{ $release->status?->label() ?? ucfirst((string) $release->status) }}</td>
                                        <td class="px-3 py-2 text-sm text-[#304458]">{{ $release->encapsulated?->label() ?? 'Unknown' }}</td>
                                        <td class="px-3 py-2 text-sm text-[#304458]">
                                            @if($release->zcVersions->count() > 0)
                                                {{ $release->zcVersions->pluck('version')->implode(', ') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-sm text-[#304458]">{{ \App\Support\LocalTime::format($release->created_at, 'date') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $releases->appends(['tab' => 'releases', 'plugins_page' => request('plugins_page'), 'threads_page' => request('threads_page'), 'posts_page' => request('posts_page')])->links() }}
                    </div>
                @endif
            </section>
        @endif

        @if($activeTab === 'threads')
            <section class="border border-[#d8dce2] bg-white p-4">
                <h2 class="text-xl font-semibold text-[#0f2133]">Started Threads</h2>
                @if(!$canViewNonPublicContributions)
                    <p class="mt-1 text-xs text-[#5c6570]">Showing publicly visible contributions only.</p>
                @endif

                @if($threads->count() === 0)
                    <p class="mt-3 text-sm text-[#5c6570]">No threads found.</p>
                @else
                    <div class="mt-3 divide-y divide-[#d8d1c8]">
                        @foreach($threads as $thread)
                            <article class="py-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#5c6570]">
                                            {{ $thread->forum ? \App\Support\LegacyTextDecoder::decode($thread->forum->name) : 'Unknown forum' }}
                                        </p>
                                        <h3 class="mt-1 text-lg font-semibold text-[#0f2133]">
                                            <a href="{{ route('threads.show', $thread) }}" class="text-[#0d5fc8] hover:underline">{{ \App\Support\LegacyTextDecoder::decode($thread->title) }}</a>
                                        </h3>
                                    </div>
                                    <p class="text-sm text-[#5c6570]">{{ \App\Support\LocalTime::format($thread->last_post_at ?? $thread->created_at) }}</p>
                                </div>
                                <dl class="mt-3 grid gap-2 text-sm text-[#304458] sm:grid-cols-3">
                                    <div>
                                        <dt class="font-bold text-[#304458]">Replies</dt>
                                        <dd>{{ number_format((int) ($thread->reply_count ?? 0)) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-bold text-[#304458]">Visible Posts</dt>
                                        <dd>{{ number_format((int) ($thread->posts_count ?? 0)) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-bold text-[#304458]">Started</dt>
                                        <dd>{{ $thread->created_at ? \App\Support\LocalTime::format($thread->created_at) : 'Unknown' }}</dd>
                                    </div>
                                </dl>
                            </article>
                        @endforeach
                    </div>
                    <div class="mt-3">
                        {{ $threads->appends(['tab' => 'threads', 'plugins_page' => request('plugins_page'), 'releases_page' => request('releases_page'), 'posts_page' => request('posts_page')])->links() }}
                    </div>
                @endif
            </section>
        @endif

        @if($activeTab === 'posts')
            <section class="border border-[#d8dce2] bg-white p-4">
                <h2 class="text-xl font-semibold text-[#0f2133]">Posts</h2>
                @if(!$canViewNonPublicContributions)
                    <p class="mt-1 text-xs text-[#5c6570]">Showing publicly visible contributions only.</p>
                @endif

                @if($posts->count() === 0)
                    <p class="mt-3 text-sm text-[#5c6570]">No posts found.</p>
                @else
                    <div class="mt-3 divide-y divide-[#d8d1c8]">
                        @foreach($posts as $post)
                            @php
                                $postThread = $post->thread;
                                $postThreadUrl = $postThread ? route('threads.show', $postThread).'#post-'.$post->id : null;
                            @endphp
                            <article class="py-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#5c6570]">
                                            {{ $postThread?->forum?->name ?? 'Unknown forum' }}
                                        </p>
                                        <h3 class="mt-1 text-lg font-semibold text-[#0f2133]">
                                            @if($postThreadUrl)
                                                <a href="{{ $postThreadUrl }}" class="text-[#0d5fc8] hover:underline">{{ $postThread?->title ?? 'Thread' }}</a>
                                            @else
                                                {{ $postThread?->title ?? 'Thread' }}
                                            @endif
                                        </h3>
                                    </div>
                                    <p class="text-sm text-[#5c6570]">{{ \App\Support\LocalTime::format($post->created_at) }}</p>
                                </div>
                                <div class="mt-3 text-sm leading-6 text-[#304458]">
                                    {{ \Illuminate\Support\Str::limit(app(\App\Support\FormattedContentRenderer::class)->plainText((string) $post->body), 280) }}
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <div class="mt-3">
                        {{ $posts->appends(['tab' => 'posts', 'plugins_page' => request('plugins_page'), 'releases_page' => request('releases_page'), 'threads_page' => request('threads_page')])->links() }}
                    </div>
                @endif
            </section>
        @endif
    </div>
@endsection
