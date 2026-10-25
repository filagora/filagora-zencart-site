@php
    $style = (string) ($payload['style'] ?? 'solid');
@endphp

@if($style === 'spacer')
    <div class="mt-6 h-4"></div>
@elseif($style === 'dashed')
    <hr class="mt-6 border-t border-dashed border-[#d8d1c8]">
@else
    <hr class="mt-6 border-t border-[#d8d1c8]">
@endif

