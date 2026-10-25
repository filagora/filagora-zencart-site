@guest
    @php
        $intended = request()->getRequestUri();
    @endphp
    <div class="mb-4 border border-[#d8dce2] bg-white px-4 py-3 text-sm text-[#5c6570]">
        If this is your first visit, check our
        <a href="https://docs.zen-cart.com/user/" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#8f3f1f] underline underline-offset-2 transition hover:text-[#7f3219]">documentation</a>.
        To post,
        <a href="{{ route('home', ['auth' => 'register', 'intended' => $intended]) }}" class="mx-1 inline-flex items-center border border-[#d8dce2] bg-white px-2.5 py-1 text-xs font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-auth-modal-url="/register">register</a>
        or
        <a href="{{ route('home', ['auth' => 'login', 'intended' => $intended]) }}" class="ml-1 inline-flex items-center border border-[#d8dce2] bg-white px-2.5 py-1 text-xs font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-auth-modal-url="/login">login</a>.
    </div>
@endguest
