<x-mail::message>
# {{ $autoPublished ? __('notifications.plugin.email.release_submitted_support.title_auto_published') : __('notifications.plugin.email.release_submitted_support.title') }}

{{ $autoPublished ? __('notifications.plugin.email.release_submitted_support.intro_auto_published') : __('notifications.plugin.email.release_submitted_support.intro') }}

**{{ __('notifications.plugin.email.release_submitted_support.plugin_label') }}** {{ $pluginName }}  
**{{ __('notifications.plugin.email.release_submitted_support.release_label') }}** v{{ $releaseVersion }}  
**{{ __('notifications.plugin.email.release_submitted_support.submitted_by_label') }}** {{ $submittedBy }}  
**{{ __('notifications.plugin.email.scan.label') }}** {{ __('notifications.plugin.email.scan.queued_support') }}

<x-mail::button :url="$actionUrl">
{{ __('notifications.plugin.email.release_submitted_support.action') }}
</x-mail::button>

{{ __('notifications.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
