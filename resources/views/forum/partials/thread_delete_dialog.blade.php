{{--
    Confirmation dialog for permanently deleting a thread. Pushed once per
    page by forum.partials.thread_delete_button; resources/js/modules/
    thread-delete-dialog.js fills it from the clicked button's data-* and
    points the form at that thread's moderate route.
--}}
@php
    $deleteFieldClass = 'w-full rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
@endphp
<div class="fixed inset-0 z-[1200] flex items-center justify-center p-4" id="thread-delete-dialog" data-thread-delete-dialog hidden>
    <div class="absolute inset-0 bg-[rgba(12,10,8,0.58)]" data-thread-delete-close></div>
    <form
        method="post"
        action=""
        class="relative z-[1] grid w-full max-w-lg gap-3 border border-[#d8dce2] bg-white p-4 shadow-[0_30px_60px_rgba(0,0,0,0.32)]"
        role="dialog"
        aria-modal="true"
        aria-labelledby="thread-delete-dialog-title"
        aria-describedby="thread-delete-dialog-summary"
        tabindex="-1"
        data-thread-delete-panel
    >
        @csrf
        <input type="hidden" name="action" value="reject_permanently">
        <input type="hidden" name="ban_duration" value="permanent">
        <input type="hidden" name="return_to" value="" data-thread-delete-return-to>

        <div class="grid gap-1">
            <h2 class="text-lg font-semibold text-[#0f2133]" id="thread-delete-dialog-title">{{ __('forum.thread_delete.title') }}</h2>
            <p class="text-sm font-semibold text-[#304458] [overflow-wrap:anywhere]" data-thread-delete-context></p>
        </div>

        <p class="border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]" id="thread-delete-dialog-summary">
            <span data-thread-delete-summary></span>
            <strong class="font-semibold">{{ __('forum.thread_delete.irreversible') }}</strong>
        </p>

        <label class="grid gap-1 text-sm font-semibold text-[#304458]" for="thread-delete-reason">
            <span>{{ __('forum.thread_delete.reason_label') }} <span class="font-normal text-[#5c6570]">{{ __('forum.thread_delete.reason_hint') }}</span></span>
            <textarea id="thread-delete-reason" name="reason" rows="2" maxlength="5000" class="{{ $deleteFieldClass }} font-normal"></textarea>
        </label>

        <div class="grid gap-2 border border-[#e7d3b8] bg-[#fbf7f0] p-3" data-thread-delete-ban>
            <label class="flex items-start gap-2 text-sm font-semibold text-[#304458]" for="thread-delete-ban">
                <input type="checkbox" id="thread-delete-ban" name="ban" value="1" class="mt-1" data-thread-delete-ban-checkbox>
                <span>
                    <span data-thread-delete-ban-label></span>
                    <span class="block text-xs font-normal text-[#5c6570]">{{ __('forum.thread_delete.ban_hint') }}</span>
                </span>
            </label>
            <label class="sr-only" for="thread-delete-ban-reason">{{ __('forum.thread_delete.ban_reason_placeholder') }}</label>
            <input type="text" id="thread-delete-ban-reason" name="ban_reason" maxlength="4000" class="{{ $deleteFieldClass }}" placeholder="{{ __('forum.thread_delete.ban_reason_placeholder') }}" data-thread-delete-ban-reason>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="button" class="inline-flex items-center rounded-control border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-thread-delete-close>{{ __('forum.thread_delete.cancel') }}</button>
            <button type="submit" class="inline-flex items-center rounded-control border border-[#9f2230] bg-[#9f2230] px-3 py-2 text-sm font-semibold text-white transition hover:border-[#7f1b26] hover:bg-[#7f1b26] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#9f2230]">{{ __('forum.thread_delete.confirm') }}</button>
        </div>
    </form>
</div>
