<x-mail::message>
# {{ __('notifications.plugin.email.submitted.title') }}

{{ $autoPublished ? __('notifications.plugin.email.submitted.intro_auto_published') : __('notifications.plugin.email.submitted.intro') }}

**{{ __('notifications.plugin.email.submitted.plugin_label') }}** {{ $pluginName }}  
**{{ __('notifications.plugin.email.submitted.initial_release_label') }}** v{{ $releaseVersion }}  
**{{ __('notifications.plugin.email.scan.label') }}** {{ __('notifications.plugin.email.scan.queued') }}

<x-mail::button :url="$actionUrl">
{{ __('notifications.plugin.email.submitted.action') }}
</x-mail::button>

{{ __('notifications.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
