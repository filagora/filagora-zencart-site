<x-mail::message>
# {{ __('notifications.plugin.email.approved.title') }}

{{ __('notifications.plugin.email.approved.intro') }}

**{{ __('notifications.plugin.email.approved.plugin_label') }}** {{ $pluginName }}

**{{ __('notifications.plugin.email.approved.id_label') }}** {{ $pluginId }}

<x-mail::button :url="$actionUrl">
{{ __('notifications.plugin.email.approved.action') }}
</x-mail::button>

{{ __('notifications.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
