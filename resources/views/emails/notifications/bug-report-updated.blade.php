<x-mail::message>
# {{ __('notifications.bug_report.email.title') }}

{{ __('notifications.bug_report.email.intro') }}

**{{ __('notifications.bug_report.email.thread_label') }}** {{ $threadTitle }}
**{{ __('notifications.bug_report.email.status_label') }}** {{ $status }}

@foreach($changes as $change)
- {{ $change }}
@endforeach

<x-mail::button :url="$bugReportUrl">
{{ __('notifications.bug_report.email.action') }}
</x-mail::button>

<x-mail::button :url="$manageSubscriptionsUrl" color="secondary">
{{ __('notifications.thread.email.updated.manage_subscriptions') }}
</x-mail::button>

{{ __('notifications.thread.email.updated.subscription_reason') }}
{{ __('notifications.thread.email.updated.manage_preferences', ['url' => $managePreferencesUrl]) }}
{{ __('notifications.thread.email.updated.unsubscribe', ['url' => $unsubscribeUrl]) }}

{{ __('notifications.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
