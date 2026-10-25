@php
    $title = trim((string) ($payload['title'] ?? ''));
    $body = trim((string) ($payload['body'] ?? ''));
    $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
@endphp

<section class="bg-[#fffdf5] py-12 sm:py-16">
    <div class="cms-page-shell !max-w-[1140px] !py-0">
        @if($title !== '' || $body !== '')
            <div class="mx-auto mb-10 max-w-3xl text-center">
                @if($title !== '')
                    <h2 class="text-4xl font-black tracking-[-0.04em] text-[#0f2133]">{{ $title }}</h2>
                @endif
                @if($body !== '')
                    <div class="mt-4 text-[1rem] leading-7 text-[#4b5968] cms-markdown">@cmsMarkdown($body)</div>
                @endif
            </div>
        @endif

        <div class="mx-auto max-w-5xl space-y-3" data-single-accordion="cms-accordion-list">
            @foreach($items as $item)
                @php
                    $itemTitle = trim((string) (is_array($item) ? ($item['title'] ?? '') : ''));
                    $points = is_array($item) && is_array($item['points'] ?? null) ? $item['points'] : [];
                @endphp
                @if($itemTitle !== '')
                    <details class="group overflow-hidden rounded-[24px] border border-[#d8c9bf] bg-white shadow-[0_6px_18px_rgba(15,33,51,0.08)] transition [&[open]>summary]:bg-[#933c1f] [&[open]>summary]:text-white [&[open]>summary:hover]:bg-[#933c1f]">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 bg-[#f4f5fb] px-5 py-4 text-xl font-semibold text-[#0f2133] marker:content-none transition-colors hover:bg-[#eceff7]">
                            <span>{{ $itemTitle }}</span>
                            <i class="fa-solid fa-chevron-down text-[#933c1f] transition-transform duration-300 group-open:rotate-180 group-open:text-white"></i>
                        </summary>
                        <ul class="grid list-none gap-3 border-t border-[#eadcd3] bg-white px-5 py-5 text-[1.02rem] md:grid-cols-2">
                            @foreach($points as $point)
                                @php
                                    $text = is_array($point) ? trim((string) ($point['text'] ?? '')) : trim((string) $point);
                                @endphp
                                @if($text !== '')
                                    <li><i class="fa-solid fa-check mr-2 text-[#933c1f]"></i>{{ $text }}</li>
                                @endif
                            @endforeach
                        </ul>
                    </details>
                @endif
            @endforeach
        </div>
    </div>

    @pushOnce('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const accordion = document.querySelector('[data-single-accordion="cms-accordion-list"]');

                if (! accordion) {
                    return;
                }

                const items = accordion.querySelectorAll('details');

                items.forEach(function (item) {
                    item.addEventListener('toggle', function () {
                        if (! item.open) {
                            return;
                        }

                        items.forEach(function (other) {
                            if (other !== item) {
                                other.open = false;
                            }
                        });
                    });
                });
            });
        </script>
    @endPushOnce
</section>
