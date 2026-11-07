@extends('layouts.main')

@section('title', 'Zen Cart Support')
@section('main_class', '')

@section('content')
    <div data-home-motion-root>
    @include('partials.home.legacy-page')

    {{-- @include('partials.home.sections.testimonials') --}}
    </div>
@endsection
