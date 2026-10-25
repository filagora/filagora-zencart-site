@extends('layouts.main')

@section('title', 'Add Poll')
@section('main_class', 'bg-[#dddddf]')

@section('content')
    @php
        $decodedThreadTitle = \App\Support\LegacyTextDecoder::decode($thread->title);
    @endphp
    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
        <div class="mb-3 text-sm text-[#5c6570]">
            <a class="text-[#8f3f1f] hover:underline" href="{{ route('forums.index') }}">Forums</a>
            / <a href="{{ route('threads.show', $thread) }}">{{ $decodedThreadTitle }}</a>
            / Add Poll
        </div>

        <header class="mb-4">
            <h1 class="text-3xl font-semibold text-[#0f2133]">Add Poll</h1>
            <p class="mt-1 text-sm text-[#5c6570]">Add a poll to "{{ $decodedThreadTitle }}".</p>
        </header>

        @if($errors->any())
            <div class="mb-3 rounded-surface border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]" role="alert" aria-live="assertive">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('threads.poll.store', $thread) }}" class="grid max-w-[900px] gap-4 rounded-surface border border-[#cabca8] bg-[#ececed] p-4">
            @csrf

            @include('thread.partials.poll_builder_fields')

            <div class="mt-2 flex items-center gap-2">
                <button type="submit" class="rounded-surface border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Add Poll</button>
                <a href="{{ route('threads.show', $thread) }}" class="inline-flex items-center rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Cancel</a>
            </div>
        </form>
    </div>
@endsection
