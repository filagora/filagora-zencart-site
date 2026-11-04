<x-mail::message>
# {{ __('notifications.plugin.email.release_rejected.title') }}

{{ __('notifications.plugin.email.release_rejected.intro') }}

**{{ __('notifications.plugin.email.release_rejected.plugin_label') }}** {{ \App\Support\MailText::plain($pluginName) }}  
**{{ __('notifications.plugin.email.release_rejected.release_label') }}** v{{ \App\Support\MailText::plain($releaseVersion) }}

**{{ __('notifications.plugin.email.release_rejected.moderator_note_label') }}**  
{{ \App\Support\MailText::plain($moderatorNote) }}

<x-mail::button :url="$actionUrl">
{{ __('notifications.plugin.email.release_rejected.action') }}
</x-mail::button>

{{ __('notifications.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
