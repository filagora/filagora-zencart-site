@props([
    'target',
    'classic' => false,
])

@php
    /*
     * Canonical control set and order, shared with the rich composer's
     * RICH_TOOLS in resources/js/modules/markdown-editor-rich.js - keep the
     * two in step so switching editors doesn't rearrange the toolbar.
     */
    $tools = [
        ['icon' => 'bold', 'title' => 'Bold', 'attrs' => ['data-markdown-tag' => 'b']],
        ['icon' => 'italic', 'title' => 'Italic', 'attrs' => ['data-markdown-tag' => 'i']],
        ['icon' => 'underline', 'title' => 'Underline', 'attrs' => ['data-markdown-tag' => 'u']],
        ['icon' => 'strikethrough', 'title' => 'Strikethrough', 'attrs' => ['data-markdown-tag' => 's']],
        'sep',
        ['icon' => 'superscript', 'title' => 'Superscript', 'attrs' => ['data-markdown-tag' => 'sup']],
        ['icon' => 'subscript', 'title' => 'Subscript', 'attrs' => ['data-markdown-tag' => 'sub']],
        ['icon' => 'highlight', 'title' => 'Highlight', 'attrs' => ['data-markdown-tag' => 'highlight']],
        'sep',
        ['icon' => 'align-left', 'title' => 'Align left', 'attrs' => ['data-markdown-tag' => 'left']],
        ['icon' => 'align-center', 'title' => 'Align centre', 'attrs' => ['data-markdown-tag' => 'center']],
        ['icon' => 'align-right', 'title' => 'Align right', 'attrs' => ['data-markdown-tag' => 'right']],
        ['icon' => 'indent', 'title' => 'Indent', 'attrs' => ['data-markdown-tag' => 'indent']],
        'sep',
        ['icon' => 'heading', 'title' => 'Heading', 'attrs' => ['data-markdown-heading' => true]],
        ['icon' => 'list-bullet', 'title' => 'Bulleted list', 'attrs' => ['data-markdown-tag' => 'list', 'data-markdown-line-list' => true]],
        ['icon' => 'list-ordered', 'title' => 'Numbered list', 'attrs' => ['data-markdown-tag' => 'list=1', 'data-markdown-ordered-list' => true]],
        ['icon' => 'quote', 'title' => 'Quote', 'attrs' => ['data-markdown-tag' => 'quote']],
        'sep',
        ['icon' => 'code', 'title' => 'Inline code', 'attrs' => ['data-markdown-tag' => 'codeinline']],
        ['icon' => 'code-block', 'title' => 'Code block', 'attrs' => ['data-markdown-tag' => 'code']],
        ['icon' => 'spoiler', 'title' => 'Spoiler', 'attrs' => ['data-markdown-spoiler' => true]],
        ['icon' => 'rule', 'title' => 'Horizontal rule', 'attrs' => ['data-markdown-hr' => true]],
        'sep',
        ['icon' => 'color', 'title' => 'Text colour', 'attrs' => ['data-markdown-color' => true]],
        ['icon' => 'size', 'title' => 'Text size', 'attrs' => ['data-markdown-size' => true]],
        ['icon' => 'font', 'title' => 'Font family', 'attrs' => ['data-markdown-font' => true]],
        'sep',
        ['icon' => 'link', 'title' => 'Insert link', 'attrs' => ['data-markdown-url' => true]],
        ['icon' => 'email', 'title' => 'Insert email address', 'attrs' => ['data-markdown-email' => true]],
        ['icon' => 'image', 'title' => 'Insert image', 'attrs' => ['data-markdown-image' => true]],
        ['icon' => 'video', 'title' => 'Insert video', 'attrs' => ['data-markdown-video' => true]],
    ];

    $buttonClass = 'inline-flex min-h-10 min-w-10 items-center justify-center rounded-control border border-transparent text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f] aria-pressed:border-[#933c1f] aria-pressed:bg-[#f7e8df] aria-pressed:text-[#933c1f]';
@endphp

<div
    class="flex flex-wrap items-center gap-1 border-b border-[#d8dce2] px-2 py-2"
    role="toolbar"
    aria-label="Markdown tools"
    @if($classic) data-markdown-classic-toolbar @endif
>
    @foreach($tools as $tool)
        @if($tool === 'sep')
            <span class="mx-1 h-5 w-px self-center bg-[#d8dce2]" aria-hidden="true"></span>
        @else
            <button
                type="button"
                class="{{ $buttonClass }}"
                data-markdown-trigger
                data-markdown-target="{{ $target }}"
                title="{{ $tool['title'] }}"
                aria-label="{{ $tool['title'] }}"
                @foreach($tool['attrs'] as $attribute => $value)@if($value === true) {{ $attribute }}@else {{ $attribute }}="{{ $value }}"@endif @endforeach
            ><x-editor-icon :name="$tool['icon']" /></button>
        @endif
    @endforeach
</div>
