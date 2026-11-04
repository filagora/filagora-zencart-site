<x-mail::message>
# {{ \App\Support\MailText::plain($autoPublished ? __('notifications.plugin.email.release_submitted_support.title_auto_published') : __('notifications.plugin.email.release_submitted_support.title')) }}

{{ \App\Support\MailText::plain($autoPublished ? __('notifications.plugin.email.release_submitted_support.intro_auto_published') : __('notifications.plugin.email.release_submitted_support.intro')) }}

**{{ __('notifications.plugin.email.release_submitted_support.plugin_label') }}** {{ \App\Support\MailText::plain($pluginName) }}  
**{{ __('notifications.plugin.email.release_submitted_support.release_label') }}** v{{ \App\Support\MailText::plain($releaseVersion) }}  
**{{ __('notifications.plugin.email.release_submitted_support.submitted_by_label') }}** {{ \App\Support\MailText::plain($submittedBy) }}  
**{{ __('notifications.plugin.email.scan.label') }}** {{ __('notifications.plugin.email.scan.queued_support') }}

<x-mail::button :url="$actionUrl">
{{ __('notifications.plugin.email.release_submitted_support.action') }}
</x-mail::button>

{{ __('notifications.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
