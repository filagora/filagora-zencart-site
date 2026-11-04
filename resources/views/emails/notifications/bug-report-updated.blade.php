<x-mail::message>
# {{ __('notifications.bug_report.email.title') }}

{{ __('notifications.bug_report.email.intro') }}

**{{ __('notifications.bug_report.email.thread_label') }}** {{ \App\Support\MailText::plain($threadTitle) }}
**{{ __('notifications.bug_report.email.status_label') }}** {{ \App\Support\MailText::plain($status) }}

@foreach($changes as $change)
- {{ \App\Support\MailText::plain($change) }}
@endforeach

<x-mail::button :url="$bugReportUrl">
{{ __('notifications.bug_report.email.action') }}
</x-mail::button>

<x-mail::button :url="$manageSubscriptionsUrl" color="secondary">
{{ __('notifications.thread.email.updated.manage_subscriptions') }}
</x-mail::button>

{{ __('notifications.thread.email.updated.subscription_reason') }}
{{ \App\Support\MailText::plain(__('notifications.thread.email.updated.manage_preferences', ['url' => $managePreferencesUrl])) }}
{{ \App\Support\MailText::plain(__('notifications.thread.email.updated.unsubscribe', ['url' => $unsubscribeUrl])) }}

{{ __('notifications.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
