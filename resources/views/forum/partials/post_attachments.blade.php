@php
    // Attachments pasted inline via the editor are already rendered where the
    // author placed them in the body (see $contentRenderer->render() above) —
    // exclude those here so the same image doesn't also show in this strip.
    $inlineAttachmentIds = [];
    if (preg_match_all('/post-attachments\/(\d+)/', (string) $post->body, $inlineMatches)) {
        $inlineAttachmentIds = array_map('intval', $inlineMatches[1]);
    }
    $strippedAttachments = $post->attachments->whereNotIn('id', $inlineAttachmentIds);
@endphp
@if($strippedAttachments->isNotEmpty())
    <div class="mt-3 flex flex-wrap gap-2 border-t border-[#d8dce2] pt-3">
        @foreach($strippedAttachments as $attachment)
            @php
                $isImage = $attachment->isImage();
                $isInlineDisplayable = $attachment->isInlineDisplayable();
                $fullUrl = $attachment->legacy_attachment_id !== null
                    ? route('legacy.attachments.download', [
                        'attachmentid' => $attachment->legacy_attachment_id,
                    ] + ($isInlineDisplayable ? ['disposition' => 'inline'] : []))
                    : route('post.attachments.show', [
                        'attachment' => $attachment->id,
                    ] + ($isInlineDisplayable ? ['disposition' => 'inline'] : []));
                $thumbnailUrl = $attachment->thumbnail_storage_path !== null
                    ? route('post.attachments.thumbnail', ['attachment' => $attachment->id])
                    : $fullUrl;
                $filename = $attachment->original_filename !== null && $attachment->original_filename !== ''
                    ? $attachment->original_filename
                    : 'Attachment #'.$attachment->id;
            @endphp

            @if($isImage)
                <a href="{{ $fullUrl }}" target="_blank" rel="noopener noreferrer" class="block overflow-hidden rounded-surface border border-[#d8dce2]">
                    <img src="{{ $thumbnailUrl }}" alt="{{ $filename }}" class="block max-h-[220px] w-auto max-w-full">
                </a>
            @else
                <a href="{{ $fullUrl }}" class="flex items-center gap-2 rounded-surface border border-[#d8dce2] bg-[#f6f3ee] px-3 py-2 text-sm text-[#1a5fd0] hover:underline">
                    <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-[#8f3f1f]" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4" stroke="currentColor" stroke-width="1.7">
                            <path d="M7.5 8.5v5a3 3 0 0 0 6 0V6.5a2 2 0 0 0-4 0v6.5a1 1 0 0 0 2 0V8" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </span>
                    <span class="truncate">{{ $filename }}</span>
                    <span class="shrink-0 text-xs text-[#5c6570]">({{ $attachment->displaySize() }})</span>
                </a>
            @endif
        @endforeach
    </div>
@endif
