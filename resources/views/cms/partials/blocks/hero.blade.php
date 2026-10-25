<section class="mt-4 rounded-xl border border-[#d8d1c8] bg-[#ececed] px-5 py-6">
    @if(!empty($payload['title']))
        <h3 class="text-2xl font-black text-[#20170f]">{{ $payload['title'] }}</h3>
    @endif
    @if(!empty($payload['body']))
        <div class="mt-3 leading-relaxed text-[#5c4a3d] cms-markdown">@cmsMarkdown($payload['body'])</div>
    @endif
    @if(!empty($payload['cta_label']) && !empty($payload['cta_url']))
        <p class="mt-4">
            <a href="{{ $payload['cta_url'] }}" class="font-semibold text-[#8f3f1f] hover:text-[#6f2f16]">{{ $payload['cta_label'] }}</a>
        </p>
    @endif
</section>

