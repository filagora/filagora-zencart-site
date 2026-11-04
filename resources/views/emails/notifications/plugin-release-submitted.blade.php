<x-mail::message>
# {{ __('notifications.plugin.email.release_submitted.title') }}

{{ \App\Support\MailText::plain($autoPublished ? __('notifications.plugin.email.release_submitted.intro_auto_published') : __('notifications.plugin.email.release_submitted.intro')) }}

**{{ __('notifications.plugin.email.release_submitted.plugin_label') }}** {{ \App\Support\MailText::plain($pluginName) }}  
**{{ __('notifications.plugin.email.release_submitted.release_label') }}** v{{ \App\Support\MailText::plain($releaseVersion) }}  
**{{ __('notifications.plugin.email.scan.label') }}** {{ __('notifications.plugin.email.scan.queued') }}

<x-mail::button :url="$actionUrl">
{{ __('notifications.plugin.email.release_submitted.action') }}
</x-mail::button>

{{ __('notifications.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
