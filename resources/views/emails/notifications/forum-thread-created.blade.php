<x-mail::message>
# {{ __('notifications.forum_thread_created.title') }}

{{ __('notifications.forum_thread_created.intro', ['author' => $authorName, 'forum' => $forumName]) }}

**{{ __('notifications.forum_thread_created.thread_label') }}** {{ $threadTitle }}

{{ $excerpt }}

<x-mail::button :url="$threadUrl">
{{ __('notifications.forum_thread_created.action') }}
</x-mail::button>

<x-mail::button :url="$manageSubscriptionsUrl" color="secondary">
{{ __('notifications.forum_thread_created.manage_subscriptions') }}
</x-mail::button>

{{ __('notifications.forum_thread_created.subscription_reason', ['forum' => $forumName]) }}
{{ __('notifications.thread.email.updated.manage_preferences', ['url' => $managePreferencesUrl]) }}
{{ __('notifications.forum_thread_created.unsubscribe', ['url' => $unsubscribeUrl]) }}

{{ __('notifications.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
