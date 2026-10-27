@extends('layouts.main')

@section('title', 'Newsletter')
@section('meta_description', 'Subscribe to the Zen Cart newsletter for occasional project news and updates.')
@section('main_class', '')

@php
    $authUser = auth()->user();
    $isSubscribed = $newsletterSubscriber?->status === \App\NewsletterSubscriberStatus::Subscribed;
@endphp

@section('content')
    <section class="bg-[color:var(--color-site-panel)] py-12 sm:py-16">
        <div class="cms-page-shell !max-w-[1140px] !py-0">
            <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.85fr)] lg:items-start">
                <div>
                    <p class="inline-flex rounded-full border border-[#933c1f] bg-[#933c1f] px-5 py-1.5 text-sm font-extrabold uppercase tracking-[0.14em] text-white shadow-[0_8px_20px_rgba(147,60,31,0.28)]">
                        Newsletter
                    </p>
                    <h1 class="mt-5 text-[clamp(2.9rem,8vw,5rem)] font-black text-[#0f2133]">Zen Cart updates by email.</h1>
                    <p class="mt-5 max-w-3xl text-[1.08rem] leading-8 text-[#435060] sm:text-[1.2rem]">
                        Receive occasional news and updates about Zen Cart. We will not sell your email address or use it for any other purpose.
                    </p>
                </div>

                <div class="rounded-2xl border border-[#e6dccb] bg-white p-6 shadow-[0_14px_28px_rgba(15,33,51,0.06)]">
                    @if(session('newsletter_status'))
                        <p class="mb-4 rounded-lg border border-[#b9d6bd] bg-[#f1fbf2] px-4 py-3 text-sm text-[#1f5130]" role="status">{{ session('newsletter_status') }}</p>
                    @endif

                    @if(session('profile_status'))
                        <p class="mb-4 rounded-lg border border-[#b9d6bd] bg-[#f1fbf2] px-4 py-3 text-sm text-[#1f5130]" role="status">{{ session('profile_status') }}</p>
                    @endif

                    @auth
                        @if($isSubscribed)
                            <p class="text-sm font-semibold text-[#0f2133]">You are subscribed.</p>
                            <p class="mt-1 text-sm text-[#5c6570]">
                                Newsletter emails are sent to <strong>{{ $authUser->email }}</strong>. You can unsubscribe at any time.
                            </p>

                            <form method="post" action="{{ route('profile.newsletter.update') }}" class="mt-5">
                                @csrf
                                <input type="hidden" name="newsletter_subscribed" value="0">
                                <button type="submit" class="rounded-lg border border-[#d8dce2] bg-white px-3.5 py-2 text-sm font-semibold text-[#0f2133] transition hover:bg-[#f4f1ea] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">
                                    Unsubscribe
                                </button>
                            </form>
                        @else
                            <p class="text-sm font-semibold text-[#0f2133]">Subscribe using your account email</p>
                            <p class="mt-1 text-sm text-[#5c6570]">We'll send newsletter emails to <strong>{{ $authUser->email }}</strong>.</p>

                            <form method="post" action="{{ route('profile.newsletter.update') }}" class="mt-5">
                                @csrf
                                <input type="hidden" name="newsletter_subscribed" value="1">

                                <label class="flex items-start gap-3 text-sm text-[#5c6570]">
                                    <input type="checkbox" name="newsletter_consent" value="1" required class="mt-1">
                                    <span>I agree to receive the Zen Cart newsletter by email.</span>
                                </label>

                                @if($errors->getBag('newsletter')->any())
                                    <p class="mt-4 text-sm text-[#dc3545]">{{ $errors->getBag('newsletter')->first() }}</p>
                                @endif

                                <button type="submit" class="ui-btn-primary mt-5">Subscribe</button>
                            </form>
                        @endif
                    @else
                        <form method="post" action="{{ route('newsletter.store') }}">
                            @csrf
                            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                            <label class="grid gap-1.5">
                                <span class="text-sm font-bold text-[#5c6570]">Name</span>
                                <input name="name" type="text" value="{{ old('name') }}" class="rounded-lg border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">
                            </label>

                            <label class="mt-4 grid gap-1.5">
                                <span class="text-sm font-bold text-[#5c6570]">Email</span>
                                <input name="email" type="email" value="{{ old('email') }}" required class="rounded-lg border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">
                            </label>

                            <label class="mt-4 flex items-start gap-3 text-sm text-[#5c6570]">
                                <input type="checkbox" name="newsletter_consent" value="1" required class="mt-1">
                                <span>I agree to receive the Zen Cart newsletter by email.</span>
                            </label>

                            @if($errors->any())
                                <p class="mt-4 text-sm text-[#dc3545]">{{ $errors->first() }}</p>
                            @endif

                            <button type="submit" class="ui-btn-primary mt-5">Send Confirmation Link</button>
                        </form>
                    @endauth
                </div>
            </div>
        </div>
    </section>
@endsection
