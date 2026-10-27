@extends('layouts.main')

@section('title', 'Member Threads')
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
            <h1 class="mt-2 text-3xl font-semibold text-[#0f2133]">Latest Started Threads</h1>
            <p class="mt-1 text-sm text-[#5c6570]">Recent visible threads started by {{ $displayName }}.</p>
        </header>

        <section class="rounded-surface border border-[#cabca8] bg-white shadow-[0_2px_6px_rgba(21,16,11,0.06)]">
            @if($threads->isEmpty())
                <div class="px-4 py-5 text-sm text-[#5c6570]">No visible started threads found.</div>
            @else
                <div class="divide-y divide-[#d8d1c8]">
                    @foreach($threads as $thread)
                        <article class="px-4 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#5c6570]">
                                        {{ $thread->forum ? \App\Support\LegacyTextDecoder::decode($thread->forum->name) : 'Unknown forum' }}
                                    </p>
                                    <h2 class="mt-1 text-lg font-semibold text-[#0f2133]">
                                        <a href="{{ route('threads.show', $thread) }}" class="text-[#0d5fc8] hover:underline">{{ \App\Support\LegacyTextDecoder::decode($thread->title) }}</a>
                                    </h2>
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

                <div class="border-t border-[#d8d1c8] px-4 py-4">
                    {{ $threads->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
