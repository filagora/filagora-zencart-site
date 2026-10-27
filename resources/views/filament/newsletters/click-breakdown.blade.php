@php
    /** @var \Illuminate\Support\Collection<int, array{label: string, clicks: int, unique_recipients: int, newsletter_block_id: int|null}> $clicks */
@endphp

@if ($clicks->isEmpty())
    <p class="text-sm text-[#52616f]">No link clicks recorded yet.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-[#d6d3d1] text-xs uppercase tracking-wide text-[#52616f]">
                    <th class="py-2 pr-4 font-semibold">Link</th>
                    <th class="py-2 pr-4 font-semibold">Clicks</th>
                    <th class="py-2 pr-4 font-semibold">Unique Recipients</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($clicks as $click)
                    <tr class="border-b border-[#eee7d8]">
                        <td class="py-2 pr-4">
                            @if ($click['newsletter_block_id'])
                                <a
                                    href="{{ \App\Filament\Resources\NewsletterBlocks\NewsletterBlockResource::getUrl('view', ['record' => $click['newsletter_block_id']]) }}"
                                    class="font-semibold text-[#8a4b19]"
                                >
                                    {{ $click['label'] }}
                                </a>
                            @else
                                {{ $click['label'] }}
                            @endif
                        </td>
                        <td class="py-2 pr-4">{{ $click['clicks'] }}</td>
                        <td class="py-2 pr-4">{{ $click['unique_recipients'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
