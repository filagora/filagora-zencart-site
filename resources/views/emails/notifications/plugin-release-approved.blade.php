<x-mail::message>
# {{ __('notifications.plugin.email.release_approved.title') }}

{{ __('notifications.plugin.email.release_approved.intro') }}

**{{ __('notifications.plugin.email.release_approved.plugin_label') }}** {{ \App\Support\MailText::plain($pluginName) }}<br>
**{{ __('notifications.plugin.email.release_approved.plugin_id_label') }}** {{ \App\Support\MailText::plain($pluginId) }}<br>
**{{ __('notifications.plugin.email.release_approved.release_label') }}** v{{ \App\Support\MailText::plain($releaseVersion) }}<br>
**{{ __('notifications.plugin.email.release_approved.release_id_label') }}** {{ \App\Support\MailText::plain($releaseId) }}

<x-mail::button :url="$actionUrl">
{{ __('notifications.plugin.email.release_approved.action') }}
</x-mail::button>

{{ __('notifications.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
