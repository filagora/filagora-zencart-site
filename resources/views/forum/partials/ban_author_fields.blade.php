{{--
    "Also ban the author" controls shared by the moderation queue forms. Posts
    ban, ban_reason, ban_duration and ban_until; the receiving action applies
    them through BanUserAction. Pass $collapsed to tuck the controls behind a
    disclosure where several forms sit side by side.
--}}
@php
    $fieldClass = 'border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
@endphp
@if($collapsed ?? false)
<details class="border border-[#e7d3b8] bg-[#fbf7f0] p-3 text-sm text-[#304458]">
    <summary class="cursor-pointer font-semibold">Also ban {{ $authorName }}…</summary>
    <div class="mt-2 grid gap-2">
@else
<div class="grid gap-2 border border-[#e7d3b8] bg-[#fbf7f0] p-3">
@endif
        <label class="flex items-center gap-2 text-sm font-semibold text-[#304458]">
            <input type="checkbox" name="ban" value="1"> Also ban {{ $authorName }}
        </label>
        <div class="grid gap-2 sm:grid-cols-[1fr_auto]">
            <input type="text" name="ban_reason" maxlength="4000" class="w-full {{ $fieldClass }}" placeholder="Ban reason">
            <select name="ban_duration" class="{{ $fieldClass }}">
                <option value="1d">1 day</option>
                <option value="7d">7 days</option>
                <option value="30d" selected>30 days</option>
                <option value="90d">90 days</option>
                <option value="1y">1 year</option>
                <option value="permanent">Permanent</option>
                <option value="custom">Custom date…</option>
            </select>
        </div>
        <label class="grid gap-1 text-xs text-[#5c6570]">
            Custom end date <span class="font-normal">(used only when “Custom date…” is selected)</span>
            <input type="datetime-local" name="ban_until" class="{{ $fieldClass }}">
        </label>
@if($collapsed ?? false)
    </div>
</details>
@else
</div>
@endif
