@if(!empty($payload['html']))
    <div class="cms-rich-content mt-4">
        {!! $payload['html'] !!}
    </div>
@elseif(!empty($payload['url']))
    <p class="mt-4">
        <a href="{{ $payload['url'] }}" class="font-semibold text-[#8f3f1f] hover:text-[#6f2f16]">{{ $payload['url'] }}</a>
    </p>
@endif

