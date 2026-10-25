@extends('layouts.main')

@section('title', 'Reset Password')
@section('main_class', 'bg-white')

@section('content')
    <div class="mx-auto max-w-[440px] px-6 py-10">
        <header class="mb-5">
            <h1 class="ui-page-title">Reset Password</h1>
        </header>

        @if ($errors->any())
            <div class="mb-3 rounded-surface border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]" role="alert" aria-live="assertive">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('password.update') }}" class="grid gap-2 rounded-surface border border-[#d8dce2] bg-white p-4" data-lock-on-submit>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <label for="reset-password-email" class="text-sm font-semibold text-[#5c6570]">Email</label>
            <input
                class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                id="reset-password-email"
                type="email"
                name="email"
                value="{{ old('email', $email) }}"
                autocomplete="email"
                autofocus
                required
                @if($errors->has('email')) aria-invalid="true" @endif
            >

            <label for="reset-password-password" class="text-sm font-semibold text-[#5c6570]">New password</label>
            <input
                class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                id="reset-password-password"
                type="password"
                name="password"
                autocomplete="new-password"
                required
                @if($errors->has('password')) aria-invalid="true" @endif
            >
            @if ($errors->has('password'))
                <p class="text-sm text-[#dc3545]">{{ $errors->first('password') }}</p>
            @endif

            <label for="reset-password-password-confirmation" class="text-sm font-semibold text-[#5c6570]">Confirm new password</label>
            <input
                class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                id="reset-password-password-confirmation"
                type="password"
                name="password_confirmation"
                autocomplete="new-password"
                required
            >

            <div class="mt-2 flex items-center gap-3">
                <button type="submit" class="rounded-surface border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Reset Password</button>
            </div>
        </form>
    </div>
@endsection
