@extends('layouts.main')

@section('title')
{{ __('profile.email_preferences.updated_title') }}
@endsection

@section('main_class', 'bg-[#dddddf]')

@section('content')
    <div class="mx-auto max-w-[760px] px-6 py-10 md:px-10">
        <section class="rounded-surface border border-[#cabca8] bg-[#ececed] p-5">
            <h1 class="text-2xl font-semibold text-[#0f2133]">{{ __('profile.email_preferences.updated_title') }}</h1>
            <p class="mt-2 text-sm text-[#5c6570]">{{ __('profile.email_preferences.updated_message', ['label' => $label]) }}</p>

            <a
                href="{{ $managePreferencesUrl }}"
                class="mt-4 inline-flex rounded-surface border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
            >
                {{ __('profile.email_preferences.manage_preferences') }}
            </a>
        </section>
    </div>
@endsection
