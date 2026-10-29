<x-mail::message>
# {{ __('notifications.thread.email.digest.title', ['prefix' => $subjectPrefix]) }}

{{ __('notifications.thread.email.digest.intro') }}

@if(count($items) > 0)
@foreach($items as $item)
- [{{ $item['title'] }}]({{ $item['url'] }})
@endforeach
@else
{{ __('notifications.thread.email.digest.empty') }}
@endif

@if($hasMore)
{{ __('notifications.thread.email.digest.has_more') }}
@endif

<x-mail::button :url="$profileUrl">
{{ __('notifications.thread.email.digest.action') }}
</x-mail::button>

{{ __('notifications.thread.email.digest.unsubscribe', ['url' => $unsubscribeUrl]) }}

{{ __('notifications.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
