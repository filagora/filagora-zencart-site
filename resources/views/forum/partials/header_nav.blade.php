@php
    $forumHeaderItems = array_values($forumHeaderMenuItems ?? []);
@endphp

{{--
    Forum shortcut links (CMS "forum_header" menu). The forum search used to
    share this row but now has its own include per page - see
    forum.partials.search_bar.
--}}
<div class="mb-5 border-b border-[#e2e6ea] pb-3">
@if($forumHeaderItems !== [])
    <nav class="pt-1" aria-label="Forum shortcuts">
        <ul class="flex flex-wrap items-center gap-y-2 text-sm">
            @foreach($forumHeaderItems as $item)
                @php
                    $itemUrl = is_string($item['url'] ?? null) ? $item['url'] : '#';
                    $itemTarget = is_string($item['target'] ?? null) ? $item['target'] : '_self';
                    $itemPath = (string) parse_url($itemUrl, PHP_URL_PATH);
                    $currentPath = request()->getPathInfo();
                    $isActive = $itemPath !== ''
                        && ($currentPath === $itemPath || str_starts_with($currentPath, rtrim($itemPath, '/').'/'));
                    $linkClass = 'inline-flex items-center px-1 py-1 font-semibold underline-offset-4 transition hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f] '.($isActive ? 'text-[#933c1f] underline' : 'text-[#0f2133] hover:text-[#8f3f1f]');
                @endphp
                <li class="flex items-center">
                    @unless($loop->first)
                        <span class="mx-4 h-4 w-px bg-[#d0d5da]" aria-hidden="true"></span>
                    @endunless
                    @if($itemPath === '/forums/mark-all-read')
                        <form method="post" action="{{ route('forums.mark-all-read') }}" class="m-0">
                            @csrf
                            <button type="submit" class="{{ $linkClass }} cursor-pointer appearance-none border-0 bg-transparent">{{ $item['label'] ?? 'Untitled' }}</button>
                        </form>
                    @else
                        <a
                            href="{{ $itemUrl }}"
                            target="{{ $itemTarget }}"
                            @if($itemTarget === '_blank') rel="noopener noreferrer" @endif
                            @if($isActive) aria-current="page" @endif
                            class="{{ $linkClass }}"
                        >{{ $item['label'] ?? 'Untitled' }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endif
</div>
