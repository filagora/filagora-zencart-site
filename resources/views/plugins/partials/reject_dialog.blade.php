{{--
    Reject dialog for the plugin moderation queue. One per page; resources/js/
    modules/plugins-index.js fills it from the clicked [data-plugin-reject-open]
    button's data-* and points the form at that item's moderate route. A note
    is required: it is sent to the author.
--}}
<div class="fixed inset-0 z-[1200] flex items-center justify-center p-4" data-plugin-reject-dialog hidden>
    <div class="absolute inset-0 bg-[rgba(12,10,8,0.58)]" data-plugin-reject-close></div>
    <form
        method="post"
        action=""
        class="relative z-[1] grid w-full max-w-lg gap-3 border border-[#d8dce2] bg-white p-4 shadow-[0_30px_60px_rgba(0,0,0,0.32)]"
        role="dialog"
        aria-modal="true"
        aria-labelledby="plugin-reject-dialog-title"
        tabindex="-1"
        data-plugin-reject-panel
    >
        @csrf
        <input type="hidden" name="action" value="reject_permanently">

        <div class="grid gap-1">
            <h2 class="text-lg font-semibold text-[#0f2133]" id="plugin-reject-dialog-title" data-plugin-reject-title></h2>
            <p class="text-sm font-semibold text-[#304458] [overflow-wrap:anywhere]" data-plugin-reject-context></p>
        </div>

        <label class="grid gap-1 text-sm font-semibold text-[#304458]" for="plugin-reject-note">
            <span>Reason <span class="font-normal text-[#5c6570]">(required; sent to the author)</span></span>
            <textarea id="plugin-reject-note" name="note" rows="4" required class="w-full rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm font-normal text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-plugin-reject-note></textarea>
        </label>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="button" class="inline-flex items-center rounded-control border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-plugin-reject-close>Cancel</button>
            <button type="submit" class="inline-flex items-center rounded-control border border-[#0b3d67] bg-[#0f4c81] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#0b3d67] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0f4c81]">Reject</button>
        </div>
    </form>
</div>
