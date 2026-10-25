@php
    $privacyPolicyService = app(\Filagora\Compliance\Services\PolicyAcceptance::class);
    $privacyPolicyRequired = $privacyPolicyService->isEnforced() && $privacyPolicyService->activePolicyVersion() !== null;
@endphp

@if ($errors->any())
    <div class="mb-3 rounded-surface border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]" role="alert" aria-live="assertive">{{ $errors->first() }}</div>
@endif

<form method="post" action="{{ route('register.store') }}" class="grid gap-2 rounded-surface border border-[#d8dce2] bg-white p-4" data-lock-on-submit>
    @csrf
    <input type="hidden" name="_auth_form" value="register">
    @if (request()->query('intended'))
        <input type="hidden" name="intended" value="{{ request()->query('intended') }}">
    @endif

    <label for="register-username" class="text-sm font-semibold text-[#5c6570]">Username</label>
    <input
        class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
        id="register-username"
        type="text"
        name="username"
        value="{{ old('username') }}"
        autocomplete="username"
        autofocus
        required
        @if($errors->has('username')) aria-invalid="true" @endif
    >
    @if ($errors->has('username'))
        <p class="text-sm text-[#dc3545]">{{ $errors->first('username') }}</p>
    @endif

    <label for="register-name" class="text-sm font-semibold text-[#5c6570]">Name</label>
    <input
        class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
        id="register-name"
        type="text"
        name="name"
        value="{{ old('name') }}"
        autocomplete="name"
        required
        @if($errors->has('name')) aria-invalid="true" @endif
    >
    @if ($errors->has('name'))
        <p class="text-sm text-[#dc3545]">{{ $errors->first('name') }}</p>
    @endif

    <label for="register-email" class="text-sm font-semibold text-[#5c6570]">Email</label>
    <input
        class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
        id="register-email"
        type="email"
        name="email"
        value="{{ old('email') }}"
        autocomplete="email"
        required
        @if($errors->has('email')) aria-invalid="true" @endif
    >
    @if ($errors->has('email'))
        <p class="text-sm text-[#dc3545]">{{ $errors->first('email') }}</p>
    @endif

    <label for="register-password" class="text-sm font-semibold text-[#5c6570]">Password</label>
    <input
        class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
        id="register-password"
        type="password"
        name="password"
        autocomplete="new-password"
        required
        @if($errors->has('password')) aria-invalid="true" @endif
    >
    @if ($errors->has('password'))
        <p class="text-sm text-[#dc3545]">{{ $errors->first('password') }}</p>
    @endif

    <label for="register-password-confirmation" class="text-sm font-semibold text-[#5c6570]">Confirm password</label>
    <input
        class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
        id="register-password-confirmation"
        type="password"
        name="password_confirmation"
        autocomplete="new-password"
        required
    >

    @if ($privacyPolicyRequired)
        @php
            $policyVersion = $privacyPolicyService->activePolicyVersion()?->version;
            $policyUrl = app(\App\Support\LegalPages::class)->privacyPageUrl();
        @endphp
        <label class="mt-1 flex items-start gap-2 text-sm text-[#5c6570]">
            <input type="checkbox" name="accept_privacy_policy" value="1" class="mt-0.5" @checked(old('accept_privacy_policy')) required>
            <span>
                @if ($policyVersion)
                    I acknowledge the privacy policy (version {{ $policyVersion }})
                @else
                    I acknowledge the privacy policy
                @endif
                (<a href="{{ $policyUrl }}" target="_blank" rel="noopener noreferrer" class="text-[#8f3f1f] hover:underline">view policy</a>)
            </span>
        </label>
        @if ($errors->has('accept_privacy_policy'))
            <p class="text-sm text-[#dc3545]">{{ $errors->first('accept_privacy_policy') }}</p>
        @endif
    @endif

    @if ($turnstileEnabled ?? false)
        <input type="hidden" name="turnstile_token" value="">
        @include('auth.partials.turnstile', [
            'enabled' => true,
            'siteKey' => config('services.turnstile.site_key', ''),
            'inputName' => 'turnstile_token',
        ])
    @endif

    <div class="mt-2 flex items-center gap-3">
        <button type="submit" class="rounded-surface border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Register</button>
    </div>
</form>

@if (Route::has('login'))
    <p class="mt-3 text-sm text-[#5c6570]">Already have an account? <a href="{{ route('login') }}" class="text-[#8f3f1f] hover:underline" data-auth-modal-url="/login">Log in</a></p>
@endif
