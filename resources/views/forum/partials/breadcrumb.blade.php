{{--
    Forums / Group / Forum / Thread / current page. Every step but the last is a link.

    @var \Filagora\Forums\Models\Forum $forum
    @var string|null $forumUrl  where the forum crumb goes; null when the forum is the current page
    @var \Filagora\Forums\Models\Thread|null $thread  linked when the current page sits below a thread
    @var string|null $current  the final, unlinked crumb
--}}
@php
    $crumbClass = 'text-[#8f3f1f] hover:underline';
    $crumbGroup = $forum->group;
    $crumbThread = $thread ?? null;
@endphp
<div class="mb-3 text-sm text-[#5c6570] [overflow-wrap:anywhere]">
    <a class="{{ $crumbClass }}" href="{{ route('forums.index') }}">Forums</a>
    @if($crumbGroup)
        / <a class="{{ $crumbClass }}" href="{{ route('forums.index') }}#forum-group-{{ $crumbGroup->id }}">{{ \App\Support\LegacyTextDecoder::decode($crumbGroup->name) }}</a>
    @endif
    @if($forumUrl ?? null)
        / <a class="{{ $crumbClass }}" href="{{ $forumUrl }}">{{ \App\Support\LegacyTextDecoder::decode($forum->name) }}</a>
    @else
        / {{ \App\Support\LegacyTextDecoder::decode($forum->name) }}
    @endif
    @if($crumbThread)
        / <a class="{{ $crumbClass }}" href="{{ route('threads.show', $crumbThread) }}">{{ \App\Support\LegacyTextDecoder::decode($crumbThread->title) }}</a>
    @endif
    @if($current ?? null)
        / {{ $current }}
    @endif
</div>
