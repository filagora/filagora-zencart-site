<x-mail::message>
# {{ __('notifications.thread.email.updated.title') }}

{{ \App\Support\MailText::plain(__('notifications.thread.email.updated.intro', ['author' => $authorName])) }}

**{{ __('notifications.thread.email.updated.thread_label') }}** {{ \App\Support\MailText::plain($threadTitle) }}

{{ \App\Support\MailText::plain($excerpt) }}

<x-mail::button :url="$threadUrl">
{{ __('notifications.thread.email.updated.action') }}
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
