<x-mail::message>
# {{ __('notifications.plugin.email.auto_publish_granted.title') }}

{{ __('notifications.plugin.email.auto_publish_granted.intro') }}

{{ __('notifications.plugin.email.auto_publish_granted.detail') }}

<x-mail::button :url="$actionUrl">
{{ __('notifications.plugin.email.auto_publish_granted.action') }}
</x-mail::button>

{{ __('notifications.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
