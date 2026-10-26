{{--
    Confirmation dialog for permanently deleting a plugin or a single release
    from the moderation queue. One per page; resources/js/modules/
    plugins-index.js fills it from the clicked [data-plugin-delete-open]
    button's data-* and points the form at that item's destroy route.
--}}
@php
    $deleteFieldClass = 'w-full rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
@endphp
<div class="fixed inset-0 z-[1200] flex items-center justify-center p-4" data-plugin-delete-dialog hidden>
    <div class="absolute inset-0 bg-[rgba(12,10,8,0.58)]" data-plugin-delete-close></div>
    <form
        method="post"
        action=""
        class="relative z-[1] grid w-full max-w-lg gap-3 border border-[#d8dce2] bg-white p-4 shadow-[0_30px_60px_rgba(0,0,0,0.32)]"
        role="dialog"
        aria-modal="true"
        aria-labelledby="plugin-delete-dialog-title"
        aria-describedby="plugin-delete-dialog-summary"
        tabindex="-1"
        data-plugin-delete-panel
    >
        @csrf
        @method('DELETE')

        <div class="grid gap-1">
            <h2 class="text-lg font-semibold text-[#0f2133]" id="plugin-delete-dialog-title" data-plugin-delete-title></h2>
            <p class="text-sm font-semibold text-[#304458] [overflow-wrap:anywhere]" data-plugin-delete-context></p>
        </div>

        <p class="border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]" id="plugin-delete-dialog-summary">
            <span data-plugin-delete-summary></span>
            <strong class="font-semibold">This cannot be undone.</strong>
        </p>

        <label class="grid gap-1 text-sm font-semibold text-[#304458]" for="plugin-delete-reason">
            <span>Reason <span class="font-normal text-[#5c6570]">(optional; kept in the log)</span></span>
            <textarea id="plugin-delete-reason" name="reason" rows="2" maxlength="5000" class="{{ $deleteFieldClass }} font-normal" data-plugin-delete-reason></textarea>
        </label>

        <div class="grid gap-2 border border-[#e7d3b8] bg-[#fbf7f0] p-3" data-plugin-delete-author>
            <label class="flex items-start gap-2 text-sm font-semibold text-[#304458]">
                <input type="checkbox" name="remove_all_by_author" value="1" class="mt-1" data-plugin-delete-all>
                <span>
                    <span data-plugin-delete-all-label></span>
                    <span class="block text-xs font-normal text-[#5c6570]" data-plugin-delete-all-hint></span>
                </span>
            </label>
            <label class="flex items-start gap-2 text-sm font-semibold text-[#304458]">
                <input type="checkbox" name="ban" value="1" class="mt-1" data-plugin-delete-ban>
                <span>
                    <span data-plugin-delete-ban-label></span>
                    <span class="block text-xs font-normal text-[#5c6570]">A permanent ban lasts until staff lift it.</span>
                </span>
            </label>
            <label class="sr-only" for="plugin-delete-ban-reason">Ban reason</label>
            <input type="text" id="plugin-delete-ban-reason" name="ban_reason" maxlength="4000" class="{{ $deleteFieldClass }}" placeholder="Ban reason" data-plugin-delete-ban-reason>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="button" class="inline-flex items-center rounded-control border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-plugin-delete-close>Cancel</button>
            <button type="submit" class="inline-flex items-center rounded-control border border-[#9f2230] bg-[#9f2230] px-3 py-2 text-sm font-semibold text-white transition hover:border-[#7f1b26] hover:bg-[#7f1b26] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#9f2230]" data-plugin-delete-confirm></button>
        </div>
    </form>
</div>
