@extends('layouts.main')

@section('title', config('app.name'))

@section('content')
    <section class="mx-auto max-w-[760px] px-6 py-16 md:px-10">
        <h1 class="text-3xl font-semibold text-[#0f2133]">{{ config('app.name') }}</h1>
        <p class="mt-3 text-[#5c6570]">This page is a placeholder until the home page is rendered from the CMS.</p>
    </section>
@endsection
