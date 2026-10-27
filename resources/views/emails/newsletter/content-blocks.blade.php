@php
    /** @var array<int, array<string, mixed>> $blocks */
    /** @var array{branding?: array<string, mixed>, newsletter?: \App\Models\Newsletter|null, unsubscribe_url?: string} $context */
    $branding = $context['branding'] ?? [];
    $newsletter = $context['newsletter'] ?? null;
    $unsubscribeUrl = $context['unsubscribe_url'] ?? '';
@endphp

@foreach ($blocks as $block)
    @switch($block['type'])
        @case('header')
            @if(($branding['logo_url'] ?? null) !== null || ($branding['header_eyebrow'] ?? '') !== '' || $newsletter !== null)
                <section style="margin:0 0 32px;">
                    @if(($branding['logo_url'] ?? null) !== null)
                        <div style="margin:0 0 18px;">
                            <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['logo_alt'] ?? '' }}" style="display:block;max-width:220px;max-height:72px;width:auto;height:auto;">
                        </div>
                    @endif
                    @if(($branding['header_eyebrow'] ?? '') !== '')
                        <p style="margin:0;color:#52616f;font-size:13px;letter-spacing:.08em;text-transform:uppercase;">{{ $branding['header_eyebrow'] }}</p>
                    @endif
                    @if($newsletter !== null)
                        <h1 style="margin:8px 0 0;color:#102033;font-size:28px;line-height:1.25;">{{ $newsletter->subject }}</h1>
                    @endif
                </section>
            @endif
            @break

        @case('text')
            <section style="margin:0 0 28px;">
                @if(($block['heading'] ?? '') !== '')
                    <h2 style="margin:0 0 12px;color:#102033;font-size:22px;line-height:1.35;">{{ $block['heading'] }}</h2>
                @endif
                @if(($block['body_html'] ?? '') !== '')
                    {!! $block['body_html'] !!}
                @endif
            </section>
            @break

        @case('image')
            @if(($block['asset_url'] ?? '') !== '')
                <figure style="margin:0 0 28px;">
                    <img
                        src="{{ $block['asset_url'] }}"
                        alt="{{ $block['asset_alt'] }}"
                        style="display:block;width:100%;max-width:616px;height:auto;border-radius:12px;border:1px solid #e6dccb;"
                    >
                    @if(($block['caption'] ?? '') !== '')
                        <figcaption style="margin-top:10px;color:#52616f;font-size:13px;line-height:1.5;">{{ $block['caption'] }}</figcaption>
                    @endif
                </figure>
            @endif
            @break

        @case('button')
            @if(($block['button_label'] ?? '') !== '' && ($block['button_url'] ?? '') !== '')
                <div style="margin:0 0 28px;">
                    <a
                        href="{{ $block['button_url'] }}"
                        style="display:inline-block;padding:12px 20px;background:#8a4b19;color:#ffffff;text-decoration:none;border-radius:999px;font-weight:600;"
                    >
                        {{ $block['button_label'] }}
                    </a>
                </div>
            @endif
            @break

        @case('footer')
            @if(($branding['footer_text'] ?? '') !== '' || (($branding['manage_link_label'] ?? '') !== '' && $unsubscribeUrl !== ''))
                <section style="margin:8px 0 0;padding:20px 0 0;border-top:1px solid #e6dccb;color:#52616f;font-size:13px;line-height:1.5;">
                    @if(($branding['footer_text'] ?? '') !== '')
                        <p style="margin:0 0 10px;">{!! nl2br(e($branding['footer_text'])) !!}</p>
                    @endif
                    @if(($branding['manage_link_label'] ?? '') !== '' && $unsubscribeUrl !== '')
                        <p style="margin:0;">
                            <a href="{{ $unsubscribeUrl }}" style="color:#8a4b19;">{{ $branding['manage_link_label'] }}</a>
                        </p>
                    @endif
                </section>
            @endif
            @break
    @endswitch
@endforeach
