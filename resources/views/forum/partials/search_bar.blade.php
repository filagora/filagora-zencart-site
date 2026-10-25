{{--
    Forum search, on its own full-width row under each page's title
    (docs/search.md, "Forum search"). Scope follows the page: pass
    $searchThread on a thread, $searchForum on a
    forum, nothing for all forums. Without JS the scope is a short native
    select of those choices. forum-search-bar.js swaps it for a pill that
    opens a popover: the same choices, then "Find a forum" type-ahead over
    every forum the viewer can search, fetched from forums.search.forum-options
    the first time the popover opens. $searchCriteria / $searchAuthorName refill
    it on the results pages. Always submits to forums.search, which redirects
    thread-scoped searches to threads.search, so it works without JS;
    resources/js/modules/forum-search-bar.js only hides the forum-only filters
    while "This thread" is selected.
--}}
@php
    /** @var \Filagora\Forums\Search\ForumSearchCriteria|null $searchCriteria */
    $searchCriteria = $searchCriteria ?? null;
    $searchThread = $searchThread ?? null;
    $searchForum = $searchForum ?? ($searchThread?->forum ?? null);
    $searchAuthorName = $searchAuthorName ?? '';
    $searchScopeSelected = $searchScopeSelected ?? ($searchThread ? 'thread:' . $searchThread->id : ($searchForum ? 'forum:' . $searchForum->id : 'all'));
    $searchUser = auth()->user();
    $searchCanIncludeUnapproved = app(\App\Services\ForumSearchVisibilityResolver::class)->canIncludeUnapproved($searchUser);
    $searchDecode = static fn (?string $value): string => \App\Support\LegacyTextDecoder::decode($value);


    $searchActiveFilters = $searchCriteria === null ? 0 : collect([
        $searchAuthorName !== '',
        $searchCriteria->since !== null,
        $searchCriteria->minReplies > 0,
        $searchCriteria->mineUserId !== null,
        $searchCriteria->sort !== 'last_post',
        $searchCriteria->postSort !== 'oldest',
        $searchCriteria->includeUnapproved,
    ])->filter()->count();
    // Which scope-specific fields show on first load; forum-search-bar.js
    // switches them when the scope changes.
    $searchInThread = str_starts_with($searchScopeSelected, 'thread:');

    $searchPlaceholder = match (true) {
        str_starts_with($searchScopeSelected, 'thread:') => __('forum.search.placeholder_thread'),
        str_starts_with($searchScopeSelected, 'forum:') && $searchForum => __('forum.search.placeholder_forum', ['forum' => $searchDecode($searchForum->name)]),
        default => __('forum.search.placeholder_all'),
    };
    $searchFieldClass = 'rounded-control border border-[#d8dce2] bg-white px-2 py-1.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
    $searchLabelClass = 'grid gap-1 text-xs font-semibold text-[#304458]';
@endphp
<form
    method="get"
    action="{{ route('forums.search') }}"
    role="search"
    class="relative grid w-full min-w-0 gap-2"
    data-forum-search-bar
    data-forum-options-url="{{ route('forums.search.forum-options') }}"
>
    <div class="flex items-stretch gap-2">
        <div class="flex min-w-0 flex-1 items-stretch rounded-control border border-[#d8dce2] bg-white focus-within:border-[#8f3f1f] focus-within:ring-2 focus-within:ring-[#8f3f1f]/20">
            <label for="forum-search-scope" class="sr-only">{{ __('forum.search.scope_label') }}</label>
            <select id="forum-search-scope" name="in" class="max-w-[42%] rounded-l-control border-0 border-r border-[#d8dce2] bg-[#faf8f4] px-2 text-xs font-semibold text-[#304458] focus:outline-none" data-forum-search-scope>
                @if($searchThread)
                    <option value="thread:{{ $searchThread->id }}" @selected($searchScopeSelected === 'thread:' . $searchThread->id)>{{ __('forum.search.scope_thread') }}</option>
                @endif
                @if($searchForum)
                    <option value="forum:{{ $searchForum->id }}" data-forum-name="{{ $searchDecode($searchForum->name) }}" @selected($searchScopeSelected === 'forum:' . $searchForum->id)>{{ $searchThread ? __('forum.search.scope_forum') : $searchDecode($searchForum->name) }}</option>
                @endif
                <option value="all" @selected($searchScopeSelected === 'all')>{{ __('forum.search.scope_all') }}</option>
            </select>
            {{-- Replaces the select once forum-search-bar.js runs; see the popover below. --}}
            <button
                type="button"
                class="inline-flex max-w-[45%] items-center gap-1.5 rounded-l-control border-0 border-r border-[#d8dce2] bg-[#faf8f4] px-2.5 text-xs font-semibold text-[#304458] transition hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                aria-haspopup="dialog"
                aria-expanded="false"
                aria-controls="forum-search-scope-popover"
                data-forum-search-pill
                hidden
            >
                <span class="sr-only">{{ __('forum.search.scope_label') }}:</span>
                <span class="truncate" data-forum-search-pill-label></span>
                <svg class="h-3 w-3 flex-none" viewBox="0 0 12 12" aria-hidden="true"><path d="M3 4.5 6 7.5 9 4.5" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
            </button>
            <label for="forum-search-q" class="sr-only">{{ __('forum.search.query_label') }}</label>
            <input
                type="search"
                id="forum-search-q"
                name="q"
                value="{{ $searchCriteria?->query }}"
                maxlength="160"
                placeholder="{{ $searchPlaceholder }}"
                class="min-w-0 flex-1 border-0 bg-transparent px-2.5 py-1.5 text-sm text-[#0f2133] placeholder:text-[#5c6570] focus:outline-none"
                data-placeholder-thread="{{ __('forum.search.placeholder_thread') }}"
                data-placeholder-forum="{{ __('forum.search.placeholder_forum', ['forum' => '__FORUM__']) }}"
                data-placeholder-all="{{ __('forum.search.placeholder_all') }}"
            >
            <button type="submit" class="rounded-r-control border-0 bg-[#0f4c81] px-3 text-sm font-semibold text-white transition hover:bg-[#0b3d67] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0f4c81]">{{ __('forum.search.submit') }}</button>
        </div>
    </div>

    <div
        id="forum-search-scope-popover"
        class="absolute left-0 top-11 z-40 grid w-80 max-w-[calc(100vw-3rem)] gap-1.5 rounded-surface border border-[#d8dce2] bg-white p-2 shadow-[0_16px_36px_rgba(15,33,51,0.16)]"
        role="dialog"
        aria-label="{{ __('forum.search.scope_label') }}"
        data-forum-search-popover
        hidden
    >
        <div class="grid gap-0.5" data-forum-search-choices></div>
        <hr class="my-0.5 border-[#e2e6ea]">
        <label for="forum-search-find" class="px-1 text-xs text-[#5c6570]">{{ __('forum.search.find_label') }}</label>
        <input
            type="text"
            id="forum-search-find"
            autocomplete="off"
            placeholder="{{ __('forum.search.find_placeholder') }}"
            role="combobox"
            aria-expanded="true"
            aria-controls="forum-search-find-results"
            aria-autocomplete="list"
            class="w-full rounded-control border border-[#d8dce2] bg-white px-2 py-1.5 text-sm text-[#0f2133] focus:border-[#8f3f1f] focus:outline-none focus:ring-2 focus:ring-[#8f3f1f]/20"
            data-forum-search-find
        >
        <ul id="forum-search-find-results" class="max-h-48 overflow-y-auto" role="listbox" aria-label="{{ __('forum.search.find_results_label') }}" data-forum-search-results></ul>
        <p class="hidden px-1 py-1 text-sm text-[#5c6570]" data-forum-search-no-match>{{ __('forum.search.find_no_match') }}</p>
        <p class="hidden px-1 py-1 text-sm text-[#5c6570]" data-forum-search-loading>{{ __('forum.search.find_loading') }}</p>
        <p class="hidden px-1 py-1 text-sm text-[#9f2230]" role="alert" data-forum-search-load-error>{{ __('forum.search.find_load_error') }}</p>
    </div>

    <details class="group" @if($searchActiveFilters > 0) open @endif>
        <summary class="inline-flex w-fit cursor-pointer list-none items-center gap-1.5 rounded-control border border-[#0b3d67] bg-[#0f4c81] px-2.5 py-1 text-xs font-semibold text-white transition hover:bg-[#0b3d67] group-open:bg-[#0b3d67] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0f4c81] [&::-webkit-details-marker]:hidden">
            {{ __('forum.search.filters') }}
            @if($searchActiveFilters > 0)
                <span class="rounded-pill bg-white px-1.5 text-[11px] leading-4 text-[#0f4c81]">{{ $searchActiveFilters }}</span>
            @endif
        </summary>
        <div class="mt-2 flex flex-wrap items-end gap-x-3 gap-y-2 rounded-control border border-[#d8dce2] bg-[#faf8f4] p-3">
            <label class="{{ $searchLabelClass }}">
                {{ __('forum.search.author_label') }}
                <span class="flex gap-1">
                    <input type="text" name="author" value="{{ $searchAuthorName }}" maxlength="255" placeholder="{{ __('forum.search.author_placeholder') }}" class="{{ $searchFieldClass }} w-36">
                    <select name="author_mode" class="{{ $searchFieldClass }}" aria-label="{{ __('forum.search.author_mode_label') }}" data-forum-search-forum-only @if($searchInThread) hidden @endif>
                        <option value="started" @selected(($searchCriteria?->authorMode ?? 'started') === 'started')>{{ __('forum.search.author_started') }}</option>
                        <option value="posted" @selected($searchCriteria?->authorMode === 'posted')>{{ __('forum.search.author_posted') }}</option>
                    </select>
                </span>
            </label>
            <label class="{{ $searchLabelClass }}">
                {{ __('forum.search.since_label') }}
                <select name="since" class="{{ $searchFieldClass }}">
                    <option value="">{{ __('forum.search.since_any') }}</option>
                    @foreach(array_keys(\Filagora\Forums\Search\ForumSearchCriteria::SINCE_OPTIONS) as $sinceKey)
                        <option value="{{ $sinceKey }}" @selected($searchCriteria?->since === $sinceKey)>{{ __('forum.search.since.' . $sinceKey) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="{{ $searchLabelClass }}" data-forum-search-forum-only @if($searchInThread) hidden @endif>
                {{ __('forum.search.min_replies_label') }}
                <input type="number" name="min_replies" min="0" value="{{ ($searchCriteria?->minReplies ?? 0) > 0 ? $searchCriteria->minReplies : '' }}" placeholder="0" class="{{ $searchFieldClass }} w-20">
            </label>
            @auth
                {{-- One checkbox in every scope: threads you started or posted in, or in a thread, your own posts. --}}
                <label class="inline-flex items-center gap-2 pb-1.5 text-xs font-semibold text-[#304458]">
                    <input type="checkbox" name="mine" value="1" @checked($searchCriteria?->mineUserId !== null)>
                    <span
                        data-forum-search-mine-label
                        data-label-thread="{{ __('forum.search.mine_posts_label') }}"
                        data-label-other="{{ __('forum.search.mine_label') }}"
                    >{{ str_starts_with($searchScopeSelected, 'thread:') ? __('forum.search.mine_posts_label') : __('forum.search.mine_label') }}</span>
                </label>
            @endauth
            @if($searchCanIncludeUnapproved)
                <label class="inline-flex items-center gap-2 pb-1.5 text-xs font-semibold text-[#304458]">
                    <input type="checkbox" name="include_unapproved" value="1" @checked($searchCriteria?->includeUnapproved)>
                    {{ __('forum.search.include_unapproved_label') }}
                </label>
            @endif
            <label class="{{ $searchLabelClass }}" data-forum-search-forum-only @if($searchInThread) hidden @endif>
                {{ __('forum.search.sort_label') }}
                <select name="sort" class="{{ $searchFieldClass }}">
                    @foreach(\Filagora\Forums\Search\ForumSearchCriteria::SORTS as $sortKey)
                        <option value="{{ $sortKey }}" @selected(($searchCriteria?->sort ?? 'last_post') === $sortKey)>{{ __('forum.search.sort.' . $sortKey) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="{{ $searchLabelClass }}" data-forum-search-thread-only @unless($searchInThread) hidden @endunless>
                {{ __('forum.search.sort_label') }}
                <select name="post_sort" class="{{ $searchFieldClass }}">
                    @foreach(\Filagora\Forums\Search\ForumSearchCriteria::POST_SORTS as $postSortKey)
                        <option value="{{ $postSortKey }}" @selected(($searchCriteria?->postSort ?? 'oldest') === $postSortKey)>{{ __('forum.search.post_sort.' . $postSortKey) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </details>
</form>
