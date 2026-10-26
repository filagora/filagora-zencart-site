@unless(config('plugins.submissions_enabled', true))
    <div class="mb-4 rounded-surface border-2 border-notice-warning-border bg-notice-warning-bg px-3 py-2 text-sm text-notice-warning-text" role="status" aria-live="polite" data-plugin-submissions-paused>
        New plugin and release submissions are temporarily paused. You can still browse and download plugins.
    </div>
@endunless
