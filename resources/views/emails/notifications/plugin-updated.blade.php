<x-mail::message>
# {{ __('notifications.plugin.email.updated.title') }}

{{ __('notifications.plugin.email.updated.intro', ['plugin' => $pluginName]) }}

**{{ __('notifications.plugin.email.updated.version_label') }}** v{{ $releaseVersion }}

<x-mail::button :url="$actionUrl">
{{ __('notifications.plugin.email.updated.action') }}
</x-mail::button>

<x-mail::button :url="$manageSubscriptionsUrl" color="secondary">
{{ __('notifications.plugin.email.updated.manage_subscriptions') }}
</x-mail::button>

{{ __('notifications.plugin.email.updated.subscription_reason') }}
{{ __('notifications.plugin.email.updated.manage_preferences', ['url' => $managePreferencesUrl]) }}
{{ __('notifications.plugin.email.updated.unsubscribe', ['url' => $unsubscribeUrl]) }}

{{ __('notifications.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
