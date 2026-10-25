@props(['name'])

@php
    $paths = match ($name) {
        'bold' => '<path d="M6 4h7a4 4 0 0 1 0 8H6z"/><path d="M6 12h8a4 4 0 0 1 0 8H6z"/>',
        'italic' => '<line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/>',
        'underline' => '<path d="M6 4v6a6 6 0 0 0 12 0V4"/><line x1="4" y1="20" x2="20" y2="20"/>',
        'strikethrough' => '<path d="M16 5H9.5a3.5 3.5 0 0 0-2.5 6"/><path d="M14 13a3.5 3.5 0 0 1-2 6H8"/><line x1="4" y1="12" x2="20" y2="12"/>',
        'superscript' => '<path d="m5 18 8-10"/><path d="m13 18-8-10"/><path d="M21 12h-4c0-1.2.5-1.8 1.5-2.4S20 8.4 20 7.5c0-.6-.4-1-1-1s-1 .4-1 1"/>',
        'subscript' => '<path d="m5 6 8 10"/><path d="m13 6-8 10"/><path d="M21 20h-4c0-1.2.5-1.8 1.5-2.4S20 16.4 20 15.5c0-.6-.4-1-1-1s-1 .4-1 1"/>',
        'highlight' => '<path d="m9 12-5 5v3h4l4.5-4.5"/><path d="M13.5 6.5 17 3l4 4-3.5 3.5"/><path d="m11 8.5 4.5 4.5"/><line x1="8" y1="21" x2="21" y2="21"/>',
        'align-left' => '<line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="14" y2="12"/><line x1="4" y1="18" x2="18" y2="18"/>',
        'align-center' => '<line x1="4" y1="6" x2="20" y2="6"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="5" y1="18" x2="19" y2="18"/>',
        'align-right' => '<line x1="4" y1="6" x2="20" y2="6"/><line x1="10" y1="12" x2="20" y2="12"/><line x1="6" y1="18" x2="20" y2="18"/>',
        'indent' => '<path d="m4 8 4 4-4 4"/><line x1="12" y1="6" x2="20" y2="6"/><line x1="12" y1="12" x2="20" y2="12"/><line x1="12" y1="18" x2="20" y2="18"/>',
        'heading' => '<path d="M6 4v16"/><path d="M18 4v16"/><path d="M6 12h12"/>',
        'list-bullet' => '<line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4.5" cy="6" r="1.4" fill="currentColor" stroke="none"/><circle cx="4.5" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="4.5" cy="18" r="1.4" fill="currentColor" stroke="none"/>',
        'list-ordered' => '<line x1="10" y1="6" x2="20" y2="6"/><line x1="10" y1="12" x2="20" y2="12"/><line x1="10" y1="18" x2="20" y2="18"/><path d="M4 5.5 5.5 5v4"/><path d="M4 15.5c0-1 2-1 2 0s-2 1.5-2 2.5h2.2"/>',
        'quote' => '<path d="M6 15h3l1.5-3V7H5v5h3z"/><path d="M15 15h3l1.5-3V7h-5v5h3z"/>',
        'code' => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/><line x1="14" y1="4" x2="10" y2="20"/>',
        'code-block' => '<path d="M8 4H7a2 2 0 0 0-2 2v4a2 2 0 0 1-2 2 2 2 0 0 1 2 2v4a2 2 0 0 0 2 2h1"/><path d="M16 4h1a2 2 0 0 1 2 2v4a2 2 0 0 0 2 2 2 2 0 0 0-2 2v4a2 2 0 0 1-2 2h-1"/>',
        'spoiler' => '<path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="M10.7 5.1A10.4 10.4 0 0 1 12 5c7 0 10 7 10 7a13.2 13.2 0 0 1-1.7 2.7"/><path d="M6.6 6.6A13.5 13.5 0 0 0 2 12s3 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/><line x1="3" y1="3" x2="21" y2="21"/>',
        'color' => '<path d="M4 19h16"/><path d="m6 15 6-12 6 12"/><path d="M8.7 12h6.6"/>',
        'size' => '<path d="m3 19 4.5-12L12 19"/><path d="M4.7 14.5h5.6"/><path d="m14 19 3-8 3 8"/><path d="M15 15.8h4"/>',
        'font' => '<path d="M4 7V5h16v2"/><path d="M9 19h6"/><path d="M12 5v14"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3A5 5 0 0 0 13.4 3.4l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7.1 7.1l1.7-1.7"/>',
        'email' => '<circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.9 7.9"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.6"/><path d="m21 15-4.6-4.6a2 2 0 0 0-2.8 0L4 20"/>',
        'video' => '<path d="m22 8-5 4 5 4z"/><rect x="2" y="6" width="15" height="12" rx="2"/>',
        'rule' => '<line x1="4" y1="12" x2="20" y2="12"/>',
        'source' => '<path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2Z"/><path d="m10 13-1.5 1.5L10 16"/><path d="m14 13 1.5 1.5L14 16"/>',
        default => '',
    };
@endphp

<svg {{ $attributes->class(['h-[18px] w-[18px] shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $paths !!}</svg>
