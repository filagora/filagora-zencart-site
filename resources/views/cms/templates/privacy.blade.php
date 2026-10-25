@extends('layouts.main')

@section('title', $page->seo_title ?: $page->title)
@section('meta_description', $page->seo_description ?: '')
@section('main_class', 'home-shell')

@section('content')
    <section class="cms-page-shell">
        <div class="cms-page-frame">
            <h1 class="text-3xl font-black text-[#20170f]">{{ $page->title }}</h1>

            @if($page->excerpt)
                <p class="mt-4 leading-relaxed text-[#5c4a3d]">{{ $page->excerpt }}</p>
            @endif

            @if($page->tags->isNotEmpty())
                <p class="mt-4 text-sm text-[#5c4a3d]">
                    @foreach($page->tags as $tag)
                        <a href="{{ route('cms.pages.tag', ['tagSlug' => $tag->slug]) }}" class="font-semibold text-[#8f3f1f] hover:text-[#6f2f16]">#{{ $tag->name }}</a>
                    @endforeach
                </p>
            @endif

            <div class="cms-page-stack mt-8">
                @forelse($page->blocks as $block)
                    <section>
                        @if($block->name)
                            <h2 class="text-xl font-bold text-[#251b12]">{{ $block->name }}</h2>
                        @endif
                        @include('cms.partials.block', ['block' => $block])
                    </section>
                @empty
                    <p class="text-[#5c4a3d]">No content blocks yet.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
