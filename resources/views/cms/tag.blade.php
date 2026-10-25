@extends('layouts.main')

@section('title', 'Pages tagged '. $tagSlug)
@section('main_class', 'home-shell')

@section('content')
    <section class="rounded-[22px] border border-[#e6d6c4] bg-white px-6 py-8 sm:px-10 sm:py-10">
        <h1 class="text-3xl font-black text-[#20170f]">Pages tagged: {{ $tagSlug }}</h1>

        <ul class="mt-6 space-y-3">
            @forelse($pages as $page)
                <li>
                    <a href="{{ route('cms.pages.show', ['slug' => $page->slug]) }}" class="font-semibold text-[#8f3f1f] hover:text-[#6f2f16]">
                        {{ $page->title }}
                    </a>
                </li>
            @empty
                <li class="text-[#5c4a3d]">No published pages found.</li>
            @endforelse
        </ul>
    </section>
@endsection
