<x-mail::message>
# {{ __('notifications.plugin.email.release_rejected.title') }}

{{ __('notifications.plugin.email.release_rejected.intro') }}

**{{ __('notifications.plugin.email.release_rejected.plugin_label') }}** {{ $pluginName }}  
**{{ __('notifications.plugin.email.release_rejected.release_label') }}** v{{ $releaseVersion }}

**{{ __('notifications.plugin.email.release_rejected.moderator_note_label') }}**  
{{ $moderatorNote }}

<x-mail::button :url="$actionUrl">
{{ __('notifications.plugin.email.release_rejected.action') }}
</x-mail::button>

{{ __('notifications.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
