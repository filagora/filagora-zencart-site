<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="forum-authenticated" content="{{ auth()->check() ? '1' : '0' }}">
    @auth
        @if(filled(config('webpush.vapid.public_key')))
            <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
        @endif
    @endauth
    <meta name="author" content="">
    @php
        // A page that has no description of its own (or sets an empty one, as a CMS
        // page without an SEO description does) falls back to the site default.
        $metaDescriptionContent = trim((string) $__env->yieldContent('meta_description'));
        // Pages name their canonical URL; CMS pages carry it in their structured data.
        $canonicalContent = trim((string) $__env->yieldContent('canonical'));
        if ($canonicalContent === '' && isset($structuredData) && is_array($structuredData)) {
            $canonicalContent = (string) (array_is_list($structuredData) ? ($structuredData[0]['url'] ?? '') : ($structuredData['url'] ?? ''));
        }
    @endphp
    {{-- Inline @section values are already escaped; e(..., false) escapes anything raw
         (a block-form @section) without double-encoding what already is. --}}
    <meta name="description" content="{!! e($metaDescriptionContent !== '' ? $metaDescriptionContent : 'Official site for the popular Zen Cart e-commerce shopping cart software.', false) !!}">
    @if($canonicalContent !== '')
        <link rel="canonical" href="{!! e($canonicalContent, false) !!}">
    @endif
    {{--
        Google Fonts + Font Awesome used to be CSS @import lines inside
        app.css, which chains them as sequential blocking requests behind
        the main stylesheet. As <link>s here they're discovered from the
        initial HTML and fetched in parallel with app.css instead. See
        docs/homepage-performance-fixes-plan.md, Stage 1.
    --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap">
    {{--
        Font Awesome specifically deferred (preload + swap-to-stylesheet on
        load) rather than a plain blocking <link> like Google Fonts above:
        Lighthouse flagged its 17.2 KiB all.min.css as still render-blocking
        even loaded in parallel with app.css. Icons are decorative, not
        above-the-fold text, so a brief delay before they appear is an
        acceptable trade for not blocking first paint. The onload swap needs
        'unsafe-inline' in script-src, already present. See
        docs/homepage-performance-fixes-plan.md, Stage 1 addendum.
    --}}
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"></noscript>
    <title>@yield('title', config('app.name', 'Forum'))</title>
    @if(isset($structuredData))
        @foreach(array_is_list($structuredData) ? $structuredData : [$structuredData] as $structuredDataDocument)
            <script type="application/ld+json">{!! json_encode($structuredDataDocument, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @endforeach
    @endif
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('assets/images/favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @stack('head')
</head>
<body>
@php
    $previewSession = app(\Filagora\Cms\Preview\PreviewSession::class);
    $previewItems = $previewSession->items();
@endphp
@if($previewItems !== [] && $previewSession->isShared())
    <div role="status" style="background:#7c2d12;color:#fff;padding:8px 16px;font:600 14px/1.4 system-ui,sans-serif;text-align:center">
        Shared preview of unpublished changes{{ $previewSession->sharedUntil() ? ' (link expires ' . $previewSession->sharedUntil()->format('j M Y H:i') . ')' : '' }}.
        Visitors still see the published site.
        @foreach($previewItems as $previewItem)
            <span style="background:rgba(255,255,255,.18);border-radius:4px;padding:2px 8px;margin:0 4px">{{ $previewItem['label'] }}@if($previewItem['active']) &middot; {{ $previewItem['active'] }}@endif</span>
        @endforeach
        <form method="post" action="{{ route('cms.preview.share.exit') }}" style="display:inline">
            @csrf
            <button type="submit" style="margin-left:8px;background:#fff;color:#7c2d12;border:0;border-radius:4px;padding:2px 10px;font:inherit;cursor:pointer">Exit preview</button>
        </form>
    </div>
@elseif($previewItems !== [])
    <div role="status" style="background:#7c2d12;color:#fff;padding:8px 16px;font:600 14px/1.4 system-ui,sans-serif;text-align:center">
        Previewing unpublished changes. Visitors still see the published site.
        @foreach($previewItems as $previewItem)
            <form method="post" action="{{ route('cms.preview.remove') }}" style="display:inline-block;margin:0 4px">
                @csrf
                <input type="hidden" name="type" value="{{ $previewItem['type'] }}">
                <input type="hidden" name="key" value="{{ $previewItem['key'] }}">
                <input type="hidden" name="return" value="{{ request()->getRequestUri() }}">
                <span style="background:rgba(255,255,255,.18);border-radius:4px;padding:2px 8px">{{ $previewItem['label'] }}
                    <button type="submit" aria-label="Stop previewing {{ $previewItem['label'] }}" style="background:none;border:0;color:#fff;cursor:pointer;font:inherit;padding:0 0 0 6px">&times;</button>
                </span>
            </form>
        @endforeach
        @foreach($previewItems as $previewItem)
            @if(count($previewItem['variants']) > 1)
                <form method="post" action="{{ route('cms.preview.variant') }}" style="display:inline-block;margin:0 4px">
                    @csrf
                    <input type="hidden" name="type" value="{{ $previewItem['type'] }}">
                    <input type="hidden" name="key" value="{{ $previewItem['key'] }}">
                    <input type="hidden" name="return" value="{{ request()->getRequestUri() }}">
                    <label style="font-weight:400">{{ $previewItem['label'] }} variant:
                        <select name="variant" onchange="this.form.submit()" style="font:inherit;color:#000;border-radius:4px">
                            @foreach($previewItem['variants'] as $variantName)
                                <option value="{{ $variantName }}" @selected($variantName === $previewItem['active'])>{{ $variantName }}</option>
                            @endforeach
                        </select>
                    </label>
                    <noscript><button type="submit">Switch</button></noscript>
                </form>
            @endif
        @endforeach
        <form method="post" action="{{ route('cms.preview.stop') }}" style="display:inline">
            @csrf
            <input type="hidden" name="return" value="{{ request()->getRequestUri() }}">
            <button type="submit" style="margin-left:8px;background:#fff;color:#7c2d12;border:0;border-radius:4px;padding:2px 10px;font:inherit;cursor:pointer">Exit preview</button>
        </form>
    </div>
@endif
@include('partials.form_submit_lock_overlay')
<a href="#main-content" class="skip-link rounded-md bg-[#0f2133] px-3 py-2 text-sm font-semibold text-white" style="position:fixed;left:0.75rem;top:0.75rem;z-index:2000;transform:translateY(-160%);opacity:0;pointer-events:none;">Skip to main content</a>
<main id="main-content" tabindex="-1" class="@yield('main_class', 'bg-[#dddddf]')">
    @include('partials.site_header')

    @yield('content')

    @include('partials.site_footer')
</main>
@stack('modals')
@stack('scripts')
@production
    {{-- The ada4zencart accessibility widget makes its own background network
    calls on every page load (config verification, geolocation lookup,
    Google Translate assets) to third-party domains. That's a real network
    dependency with no value in local/CI environments, and can make pages
    appear to hang until those requests resolve or are aborted, so it's
    limited to production. See docs/deployment.md for its CSP requirements. --}}
    <script>!function(){function e(){var e=document.createElement("script");e.type="text/javascript",e.async=!0,e.setAttribute("embed-id","eccd7930-740b-4346-9100-4ba4bc2e5a72"),e.src="https://widget.ada4zencart.com/embed-scripts/eccd7930-740b-4346-9100-4ba4bc2e5a72",window.top.document.getElementsByTagName("body")[0].appendChild(e)}"complete"===document.readyState||"interactive"===document.readyState?e():document.addEventListener("DOMContentLoaded",e)}();</script>
@endproduction
</body>
</html>
