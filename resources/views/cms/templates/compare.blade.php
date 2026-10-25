@extends('layouts.main')

@section('title', $page->seo_title ?: $page->title)
@section('meta_description', $page->seo_description ?: '')
@section('main_class', '')

@section('content')
    @forelse($page->blocks as $block)
        @include('cms.partials.block', ['block' => $block])
    @empty
        <section class="cms-page-shell">
            <div class="cms-page-frame">
                <h1 class="text-3xl font-black text-[#20170f]">{{ $page->title }}</h1>
                <p class="mt-4 text-[#5c4a3d]">No content blocks yet.</p>
            </div>
        </section>
    @endforelse
@endsection
