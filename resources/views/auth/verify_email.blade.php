@extends('layouts.main')

@section('title', 'Verify Email')
@section('main_class', 'bg-white')

@section('content')
    <div class="mx-auto max-w-[440px] px-6 py-10">
        <header class="mb-5">
            <h1 class="ui-page-title">Verify Your Email</h1>
            <p class="ui-page-subtitle mt-2">We've sent a verification link to your email address. Click the link to continue.</p>
        </header>

        @if (session('status'))
            <div class="mb-3 rounded-surface border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133]" role="status" aria-live="polite">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="mb-3 rounded-surface border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]" role="alert" aria-live="assertive">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('verification.send') }}" class="grid gap-2 rounded-surface border border-[#d8dce2] bg-white p-4" data-lock-on-submit>
            @csrf

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-surface border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Resend Verification Email</button>
            </div>
        </form>
    </div>
@endsection
