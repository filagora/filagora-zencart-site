@extends('layouts.main')

@section('title', 'Member Posts')
@section('main_class', 'bg-[#dddddf]')

@section('content')
    @php
        $displayName = $member->username ?: $member->name ?: 'User #'.$member->id;
    @endphp
    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
        <header class="mb-5">
            <a
                href="{{ route('members.show', $member) }}"
                class="inline-flex items-center text-sm font-semibold text-[#8f3f1f] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
            >Back to Member Profile</a>
            <h1 class="mt-2 text-3xl font-semibold text-[#0f2133]">Latest Posts</h1>
            <p class="mt-1 text-sm text-[#5c6570]">Recent visible forum posts by {{ $displayName }}.</p>
        </header>

        <section class="rounded-surface border border-[#cabca8] bg-white shadow-[0_2px_6px_rgba(21,16,11,0.06)]">
            @if($posts->isEmpty())
                <div class="px-4 py-5 text-sm text-[#5c6570]">No visible posts found.</div>
            @else
                <div class="divide-y divide-[#d8d1c8]">
                    @foreach($posts as $post)
                        @php
                            $thread = $post->thread;
                            $threadUrl = $thread ? route('threads.show', $thread).'#post-'.$post->id : null;
                        @endphp
                        <article class="px-4 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#5c6570]">
                                        {{ $thread?->forum?->name ?? 'Unknown forum' }}
                                    </p>
                                    <h2 class="mt-1 text-lg font-semibold text-[#0f2133]">
                                        @if($threadUrl)
                                            <a href="{{ $threadUrl }}" class="text-[#0d5fc8] hover:underline">{{ $thread?->title ?? 'Thread' }}</a>
                                        @else
                                            {{ $thread?->title ?? 'Thread' }}
                                        @endif
                                    </h2>
                                </div>
                                <p class="text-sm text-[#5c6570]">{{ \App\Support\LocalTime::format($post->created_at) }}</p>
                            </div>
                            <div class="mt-3 text-sm leading-6 text-[#304458]">
                                {{ \Illuminate\Support\Str::limit(app(\App\Support\FormattedContentRenderer::class)->plainText((string) $post->body), 280) }}
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="border-t border-[#d8d1c8] px-4 py-4">
                    {{ $posts->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
