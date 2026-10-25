@php
    $headers = is_array($payload['headers'] ?? null) ? $payload['headers'] : [];
    $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
@endphp

<section class="overflow-hidden py-12 sm:py-16">
    <div class="mx-auto w-full max-w-[1140px] px-4">
        <div class="mb-10 flex justify-center text-center">
            <div class="w-full lg:w-10/12">
                <div class="ui-section-title motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none">
                    @if(!empty($payload['eyebrow']))
                        <div class="ui-section-kicker mx-auto mb-2 inline-flex rounded-full border border-[#933c1f] bg-[#933c1f] px-5 py-1.5 text-sm font-extrabold uppercase tracking-[0.14em] text-white shadow-[0_8px_20px_rgba(147,60,31,0.28)]">
                            {{ $payload['eyebrow'] }}
                        </div>
                    @endif
                    @if(!empty($payload['title']))
                        <h2 class="text-4xl font-bold text-[#0f2133]">{{ $payload['title'] }}</h2>
                    @endif
                    @if(!empty($payload['body']))
                        <div class="ui-section-desc cms-markdown">@cmsMarkdown($payload['body'])</div>
                    @endif
                </div>
            </div>
        </div>

        @if($headers !== [] && $rows !== [])
            <div class="motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none">
                <div class="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-[#212529] text-white">
                            <tr>
                                @foreach($headers as $index => $header)
                                    @php
                                        $label = is_array($header) ? trim((string) ($header['label'] ?? '')) : trim((string) $header);
                                        $emphasize = is_array($header) ? (bool) ($header['emphasize'] ?? false) : false;
                                        $cellClasses = $index === 0
                                            ? 'px-4 py-3'
                                            : 'px-4 py-3 text-center text-[#cfd4da]';

                                        if ($emphasize) {
                                            $cellClasses .= ' border-x border-[#6c757d] bg-[#933c1f] text-white';
                                        }
                                    @endphp
                                    <th class="{{ $cellClasses }}">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                @php
                                    $feature = is_array($row) ? trim((string) ($row['feature'] ?? '')) : '';
                                    $values = is_array($row) && is_array($row['values'] ?? null) ? $row['values'] : [];
                                    $highlightColumn = is_array($row) ? (int) ($row['highlight_column'] ?? 0) : 0;
                                @endphp
                                <tr class="border-t">
                                    <td class="px-4 py-3 font-bold">{{ $feature }}</td>
                                    @foreach($values as $columnIndex => $value)
                                        @php
                                            $columnNumber = $columnIndex + 1;
                                            $text = trim((string) (is_array($value) ? ($value['value'] ?? '') : $value));
                                            $classes = 'px-4 py-3 text-center';

                                            if ($highlightColumn === $columnNumber) {
                                                $classes .= ' border-x font-bold text-emerald-600';
                                            }

                                            if (str_contains(strtolower($text), 'platform dependent') || str_contains(strtolower($text), 'blog plugin')) {
                                                $classes .= ' text-rose-600';
                                            }
                                        @endphp
                                        <td class="{{ $classes }}">{{ $text }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</section>
