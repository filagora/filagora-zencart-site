@php
    $ctaLabel = trim((string) ($payload['cta_label'] ?? ''));
    $ctaUrl = trim((string) ($payload['cta_url'] ?? ''));
    $iconClass = trim((string) ($payload['icon_class'] ?? 'fa-solid fa-users-gear'));
@endphp

<section class="bg-[#ddd] py-12 sm:py-16">
    <div class="mx-auto w-full max-w-[1140px] px-4">
        <div class="flex justify-center">
            <div class="w-full lg:w-10/12">
                <div class="overflow-hidden rounded-2xl shadow-lg motion-safe:animate-[home-fade-in-up_.7s_ease-out_both] motion-reduce:animate-none">
                    <div class="grid grid-cols-1 md:grid-cols-12">
                        <div class="flex items-center justify-center bg-[#212529] p-10 md:col-span-4">
                            <i class="{{ $iconClass }} fa-5x text-[#933c1f]"></i>
                        </div>
                        <div class="bg-white p-10 md:col-span-8">
                            @if(!empty($payload['title']))
                                <h3 class="mb-3 text-3xl font-bold">{{ $payload['title'] }}</h3>
                            @endif
                            @if(!empty($payload['body']))
                                <div class="mb-4 text-[#6c757d] cms-markdown">@cmsMarkdown($payload['body'])</div>
                            @endif
                            @if($ctaLabel !== '' && $ctaUrl !== '')
                                <a href="{{ $ctaUrl }}" class="ui-btn-primary">
                                    {{ $ctaLabel }}
                                    <i class="fa-solid fa-comments ml-2"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
