@if (session('status'))
    <div class="mb-3 rounded-surface border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133]" role="status" aria-live="polite">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="mb-3 rounded-surface border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]" role="alert" aria-live="assertive">{{ $errors->first() }}</div>
@endif

<form method="post" action="{{ route('password.email') }}" class="grid gap-2 rounded-surface border border-[#d8dce2] bg-white p-4" data-lock-on-submit>
    @csrf

    <label for="forgot-password-email" class="text-sm font-semibold text-[#5c6570]">Email</label>
    <input
        class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
        id="forgot-password-email"
        type="email"
        name="email"
        value="{{ old('email') }}"
        autocomplete="email"
        autofocus
        required
        @if($errors->has('email')) aria-invalid="true" @endif
    >

    <div class="mt-2 flex items-center gap-3">
        <button type="submit" class="rounded-surface border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Send Reset Link</button>
        @if (Route::has('login'))
            <a href="{{ route('login') }}" class="text-sm text-[#8f3f1f] hover:underline" data-auth-modal-url="/login">Back to log in</a>
        @endif
    </div>
</form>
