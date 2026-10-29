<x-mail::message>
# {{ __('notifications.plugin.email.rejected.title') }}

{{ __('notifications.plugin.email.rejected.intro') }}

**{{ __('notifications.plugin.email.rejected.plugin_label') }}** {{ $pluginName }}

**{{ __('notifications.plugin.email.rejected.moderator_note_label') }}**  
{{ $moderatorNote }}

<x-mail::button :url="$actionUrl">
{{ __('notifications.plugin.email.rejected.action') }}
</x-mail::button>

{{ __('notifications.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
