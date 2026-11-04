<x-mail::message>
# {{ __('notifications.forum_thread_created.title') }}

{{ \App\Support\MailText::plain(__('notifications.forum_thread_created.intro', ['author' => $authorName, 'forum' => $forumName])) }}

**{{ __('notifications.forum_thread_created.thread_label') }}** {{ \App\Support\MailText::plain($threadTitle) }}

{{ \App\Support\MailText::plain($excerpt) }}

<x-mail::button :url="$threadUrl">
{{ __('notifications.forum_thread_created.action') }}
</x-mail::button>

<x-mail::button :url="$manageSubscriptionsUrl" color="secondary">
{{ __('notifications.forum_thread_created.manage_subscriptions') }}
</x-mail::button>

{{ \App\Support\MailText::plain(__('notifications.forum_thread_created.subscription_reason', ['forum' => $forumName])) }}
{{ \App\Support\MailText::plain(__('notifications.thread.email.updated.manage_preferences', ['url' => $managePreferencesUrl])) }}
{{ \App\Support\MailText::plain(__('notifications.forum_thread_created.unsubscribe', ['url' => $unsubscribeUrl])) }}

{{ __('notifications.mail.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
