@extends('layouts.main')

@section('title', 'Newsletter Confirmed')
@section('meta_description', 'Your Zen Cart newsletter subscription has been confirmed.')
@section('main_class', '')

@section('content')
    <section class="bg-[color:var(--color-site-panel)] py-12 sm:py-16">
        <div class="cms-page-shell !max-w-[760px] !py-0">
            <div class="rounded-2xl border border-[#e6dccb] bg-white p-8 text-center shadow-[0_14px_28px_rgba(15,33,51,0.06)]">
                <h1 class="text-4xl font-black text-[#0f2133]">Subscription confirmed.</h1>
                <p class="mt-4 text-[1rem] leading-7 text-[#4b5968]">You are now subscribed to the Zen Cart newsletter.</p>
                <a href="{{ route('home') }}" class="ui-btn-primary mt-6">Return Home</a>
            </div>
        </div>
    </section>
@endsection
