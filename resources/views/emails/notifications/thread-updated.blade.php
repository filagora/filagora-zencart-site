<x-mail::message>
# {{ __('notifications.thread.email.updated.title') }}

{{ __('notifications.thread.email.updated.intro', ['author' => $authorName]) }}

**{{ __('notifications.thread.email.updated.thread_label') }}** {{ $threadTitle }}

{{ $excerpt }}

<x-mail::button :url="$threadUrl">
{{ __('notifications.thread.email.updated.action') }}
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
