@extends('layouts.main')

@section('title', $page->seo_title ?: $page->title)
@section('meta_description', $page->seo_description ?: '')
@section('main_class', '')

@section('content')
    @php
        $homeBlockTypes = [
            'home_hero',
            'home_value_grid',
            'home_included_grid',
            'home_deploy_steps',
            'home_feature_columns',
            'home_compare_section',
        ];
        $recognizedBlockTypes = [...$homeBlockTypes, 'stat_strip'];
        $hasOnlyRecognizedBlocks = $page->blocks->isNotEmpty()
            && $page->blocks->every(fn ($block): bool => in_array(app(\Filagora\Cms\BlockTypes\BlockTypeRegistry::class)->normalize($block->type), $recognizedBlockTypes, true));
        $hasHomeBlocks = $page->blocks->contains(fn ($block): bool => in_array(app(\Filagora\Cms\BlockTypes\BlockTypeRegistry::class)->normalize($block->type), $homeBlockTypes, true));
        $statStripBlock = $page->blocks->first(fn ($block): bool => app(\Filagora\Cms\BlockTypes\BlockTypeRegistry::class)->is($block->type, 'stat_strip'));
    @endphp

    <div data-home-motion-root>
        @if($hasOnlyRecognizedBlocks && $hasHomeBlocks)
            @foreach($page->blocks as $block)
                @include('cms.partials.block', ['block' => $block])
            @endforeach
        @else
            @include('partials.home.sections.intro')

            @if($statStripBlock !== null)
                @include('cms.partials.block', ['block' => $statStripBlock])
            @else
                @include('partials.home.sections.why')
            @endif

            @include('partials.home.sections.included')
            @include('partials.home.sections.deploy')
            @include('partials.home.sections.advantage_core')
            @include('partials.home.sections.advantage_plugins')
            @include('partials.home.sections.compare')
        @endif
    </div>
@endsection
