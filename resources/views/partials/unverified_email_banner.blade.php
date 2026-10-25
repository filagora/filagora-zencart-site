@if(auth()->check() && !auth()->user()?->hasVerifiedEmail())
    @php
        $intended = request()->getRequestUri();
        $message = trim((string) ($message ?? ''));
    @endphp
    <div class="mb-3 rounded-lg border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]" role="status" aria-live="polite">
        {{ $message !== '' ? $message : 'Your email is not verified. Verify your email to continue using protected actions.' }}
        <a href="{{ route('verification.notice', ['intended' => $intended]) }}" class="ml-2 inline-flex items-center rounded-md border border-[#cabca8] bg-white px-2.5 py-1 text-xs font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">
            Verify email
        </a>
    </div>
@endif
