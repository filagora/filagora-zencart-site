<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \App\Support\LegacyTextDecoder::decode($thread->title) }} - Attachments</title>
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="min-h-screen bg-[#d7d7d8] text-[#0f2133]">
    <main class="mx-auto max-w-[920px] px-4 py-5">
        <section class="overflow-hidden rounded-control border border-[#3e4348] bg-white shadow-[0_14px_32px_rgba(15,33,51,0.16)]">
            <header class="flex items-center justify-between gap-4 bg-[linear-gradient(180deg,#5c636a_0%,#2e3439_100%)] px-4 py-2.5 text-white">
                <div>
                    <h1 class="text-base font-semibold">Attachments</h1>
                    <p class="text-xs text-white/75">{{ \App\Support\LegacyTextDecoder::decode($thread->title) }}</p>
                </div>
                <div class="text-right text-xs text-white/85">
                    Total Attachments: {{ $totalAttachments }}
                </div>
            </header>

            @if($attachments->isEmpty())
                <div class="px-4 py-6 text-sm text-[#5f6772]">
                    No visible attachments were found for this thread.
                </div>
            @else
                <ul class="divide-y divide-[#d9dce0]">
                    @foreach($attachments as $attachment)
                        @php
                            $filename = $attachment->original_filename !== null && $attachment->original_filename !== ''
                                ? $attachment->original_filename
                                : 'Attachment #'.$attachment->id;
                            $isInlineDisplayable = $attachment->isInlineDisplayable();
                            $attachmentUrl = $attachment->legacy_attachment_id !== null
                                ? route('legacy.attachments.download', [
                                    'attachmentid' => $attachment->legacy_attachment_id,
                                ] + ($isInlineDisplayable ? ['disposition' => 'inline'] : []))
                                : route('post.attachments.show', [
                                    'attachment' => $attachment->id,
                                ] + ($isInlineDisplayable ? ['disposition' => 'inline'] : []));
                            $displaySize = $attachment->displaySize();
                        @endphp
                        <li class="grid gap-3 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
                            <div class="min-w-0">
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center text-[#8f3f1f]" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4" stroke="currentColor" stroke-width="1.7">
                                            <path d="M7.5 8.5v5a3 3 0 0 0 6 0V6.5a2 2 0 0 0-4 0v6.5a1 1 0 0 0 2 0V8" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </span>
                                    <div class="min-w-0">
                                        <a
                                            href="{{ $attachmentUrl }}"
                                            class="block truncate text-[15px] text-[#1a5fd0] hover:underline"
                                            @if($isInlineDisplayable)
                                                target="_blank" rel="noopener noreferrer"
                                            @endif
                                        >
                                            {{ $filename }}
                                        </a>
                                        <p class="mt-1 text-[11px] text-[#5c6570]">
                                            {{ $isInlineDisplayable ? 'Opens in a new page' : 'Downloads file' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <dl class="grid shrink-0 grid-cols-[auto_auto] gap-x-3 text-[11px] leading-4 text-[#5f6772]">
                                <dt>Views:</dt>
                                <dd class="text-right">{{ number_format((int) ($attachment->download_count ?? 0)) }}</dd>
                                <dt>Size:</dt>
                                <dd class="text-right">{{ $displaySize }}</dd>
                            </dl>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </main>
</body>
</html>
