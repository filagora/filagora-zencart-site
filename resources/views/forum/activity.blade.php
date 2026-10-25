@extends('layouts.main')

@section('title', $pageTitle)
@section('main_class', 'bg-white')

@section('content')
    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
        @include('forum.partials.unverified_banner')
        @include('forum.partials.header_nav')

        @include('forum.partials.moderation_queue')

        <div class="mb-3 text-sm text-[#5c6570]">
            <a class="text-[#8f3f1f] hover:underline" href="{{ route('forums.index') }}">Forums</a>
        </div>

        <header class="mb-5">
            <h1 class="ui-page-title">{{ $pageTitle }}</h1>
            <p class="ui-page-subtitle mt-2">{{ $pageDescription }}</p>
        </header>

        <div class="mb-6">
            @include('forum.partials.search_bar')
        </div>

        @include('forum.partials.thread_pagination', ['threads' => $threads, 'position' => 'top'])
        @include('forum.partials.thread_list', [
            'threads' => $threads,
            'subscribedThreadIds' => $subscribedThreadIds ?? [],
            'showSubscribedIndicator' => true,
        ])
        @include('forum.partials.thread_pagination', ['threads' => $threads, 'position' => 'bottom'])
    </div>
@endsection
