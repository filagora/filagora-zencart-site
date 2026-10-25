@php
    $contentRenderer = app(\App\Support\FormattedContentRenderer::class);
    $siteNotificationSeverityClasses = [
        'info' => 'bg-[color:var(--color-notice-info-bg)] border-[color:var(--color-notice-info-border)] text-[color:var(--color-notice-info-text)]',
        'success' => 'bg-[color:var(--color-notice-success-bg)] border-[color:var(--color-notice-success-border)] text-[color:var(--color-notice-success-text)]',
        'warning' => 'bg-[color:var(--color-notice-warning-bg)] border-[color:var(--color-notice-warning-border)] text-[color:var(--color-notice-warning-text)]',
        'critical' => 'bg-[color:var(--color-notice-critical-bg)] border-[color:var(--color-notice-critical-border)] text-[color:var(--color-notice-critical-text)]',
    ];
@endphp

@foreach(($activeSiteNotifications ?? []) as $siteNotification)
    <div
        class="border-b {{ $siteNotificationSeverityClasses[$siteNotification->severity->value] ?? $siteNotificationSeverityClasses['info'] }}"
        data-site-notification
        data-site-notification-id="{{ $siteNotification->id }}"
        data-site-notification-persistent="{{ $siteNotification->is_persistent ? '1' : '0' }}"
        role="status"
        aria-live="polite"
        @if(in_array($siteNotification->id, $dismissedSiteNotificationIds ?? [], true)) hidden @endif
    >
        <div class="mx-auto flex max-w-[1600px] flex-wrap items-start justify-between gap-3 px-6 py-3 text-sm md:px-10">
            <div class="min-w-0 flex-1 [&_a]:font-semibold [&_a]:underline [&_code]:rounded-control [&_code]:bg-black/10 [&_code]:px-1 [&_code]:py-0.5 [&_strong]:font-bold">
                <span>{!! $contentRenderer->render($siteNotification->body) !!}</span>
            </div>
            @if($siteNotification->is_dismissible)
                <button
                    type="button"
                    class="shrink-0 cursor-pointer rounded-pill border border-current/30 px-2 py-1 text-xs font-semibold transition hover:bg-black/5"
                    data-site-notification-dismiss
                    aria-label="Dismiss notification"
                >&times;</button>
            @endif
        </div>
    </div>
@endforeach
