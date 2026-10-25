@if ($errors->any() && ! $errors->has('otp'))
    <div class="mb-3 rounded-surface border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]" role="alert" aria-live="assertive">{{ $errors->first() }}</div>
@endif

@if ($pendingUser)
    <p class="mb-3 text-sm text-[#5c6570]">Enter the code from your authenticator app, or a recovery code.</p>

    <form method="post" action="{{ route('login.two-factor.store') }}" class="grid gap-2 rounded-surface border border-[#d8dce2] bg-white p-4" data-lock-on-submit>
        @csrf

        <label for="login-otp" class="text-sm font-semibold text-[#5c6570]">Authentication code</label>
        <input
            class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
            id="login-otp"
            type="text"
            name="otp"
            inputmode="numeric"
            autocomplete="one-time-code"
            autofocus
            required
            aria-describedby="{{ $errors->has('otp') ? 'login-otp-error' : '' }}"
            @if($errors->has('otp')) aria-invalid="true" @endif
        >
        @if ($errors->has('otp'))
            <p id="login-otp-error" class="text-sm text-[#dc3545]">{{ $errors->first('otp') }}</p>
        @endif

        <div class="mt-2 flex items-center gap-3">
            <button type="submit" class="rounded-surface border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Verify &amp; Log In</button>
        </div>
    </form>

    <form method="post" action="{{ route('login.two-factor.cancel') }}" class="mt-3">
        @csrf
        <button type="submit" class="text-sm text-[#8f3f1f] hover:underline">Not you? Start over.</button>
    </form>
@else
    <form method="post" action="{{ route('login.store') }}" class="grid gap-2 rounded-surface border border-[#d8dce2] bg-white p-4" data-lock-on-submit>
        @csrf
        <input type="hidden" name="_auth_form" value="login">
        @if (request()->query('intended'))
            <input type="hidden" name="intended" value="{{ request()->query('intended') }}">
        @endif

        <label for="login-username" class="text-sm font-semibold text-[#5c6570]">Username</label>
        <input
            class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
            id="login-username"
            type="text"
            name="username"
            value="{{ old('username') }}"
            autocomplete="username"
            autofocus
            required
            @if($errors->has('username')) aria-invalid="true" @endif
        >

        <label for="login-password" class="text-sm font-semibold text-[#5c6570]">Password</label>
        <input
            class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
            id="login-password"
            type="password"
            name="password"
            autocomplete="current-password"
            required
        >

        <label class="mt-1 flex items-center gap-2 text-sm text-[#5c6570]">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            Remember me
        </label>

        <div class="mt-2 flex items-center gap-3">
            <button type="submit" class="rounded-surface border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Log In</button>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm text-[#8f3f1f] hover:underline" data-auth-modal-url="/password/forgot">Forgot password?</a>
            @endif
        </div>
    </form>

    @if (Route::has('register'))
        <p class="mt-3 text-sm text-[#5c6570]">Don't have an account? <a href="{{ route('register') }}" class="text-[#8f3f1f] hover:underline" data-auth-modal-url="/register">Register</a></p>
    @endif
@endif
