@php
    $testimonials = [
        ['name' => 'John Doe', 'role' => 'Team Leader', 'text' => 'There are many variations of passages of Lorem Ipsum available, but majority have suffered alteration in some form, by injected humour.'],
        ['name' => 'Jane Smith', 'role' => 'Store Owner', 'text' => 'Setup was straightforward and we were able to launch quickly. The plugin ecosystem saved us a lot of custom work.'],
        ['name' => 'Alex Rivera', 'role' => 'Developer', 'text' => 'The codebase is practical and flexible. We can extend what we need without fighting the platform.'],
        ['name' => 'Morgan Lee', 'role' => 'Operations Manager', 'text' => 'Order flow and admin capabilities are strong enough for day-to-day business operations.'],
        ['name' => 'Taylor Brooks', 'role' => 'Agency Partner', 'text' => 'For clients who need ownership and long-term control, this is a reliable foundation.'],
        ['name' => 'Sam Patel', 'role' => 'Merchant', 'text' => 'We moved away from subscription lock-in and finally have full control over our storefront roadmap.'],
    ];
@endphp

<section class="bg-[#f2efe3] py-12 sm:py-16" data-legacy-testimonials>
    <div class="mx-auto max-w-[1320px] px-6 md:px-10">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-5xl font-extrabold text-[#051a30]">What our users say?</h2>
            <div class="hidden items-center gap-2 md:flex">
                <button type="button" data-slider-prev class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-[#c8bcaa] bg-[#f8f5ea] text-[#1f2937] transition hover:bg-[#ede6d8]" aria-label="Previous testimonial">‹</button>
                <button type="button" data-slider-next class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-[#c8bcaa] bg-[#f8f5ea] text-[#1f2937] transition hover:bg-[#ede6d8]" aria-label="Next testimonial">›</button>
            </div>
        </div>

        <div class="mt-10 overflow-hidden" data-slider-viewport>
            <div class="flex transition-transform duration-300 ease-out" data-slider-track>
                @foreach ($testimonials as $item)
                    <article class="w-full shrink-0 px-2 md:w-1/2 xl:w-1/3">
                        <div class="h-full rounded-3xl border border-[#d7cfc2] bg-[#f8f5ea] p-6">
                            <div class="flex items-center gap-4">
                                <img src="{{ asset('assets/images/profile_1.jpg') }}" alt="{{ $item['name'] }}" class="h-14 w-14 rounded-full object-cover">
                                <div>
                                    <h3 class="text-xl font-bold text-[#111f2f]">{{ $item['name'] }}</h3>
                                    <p class="text-sm text-[#6b7280]">{{ $item['role'] }}</p>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center gap-1">
                                @for ($s = 0; $s < 5; $s++)
                                    <img src="{{ asset('assets/images/icon/star.svg') }}" alt="" class="h-4 w-4">
                                @endfor
                            </div>

                            <p class="mt-4 text-base leading-relaxed text-[#374151]">{{ $item['text'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        <div class="mt-6 flex justify-center gap-2" data-slider-dots></div>
    </div>
</section>

