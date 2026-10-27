<?php

namespace App\Sanitiser;

/**
 * What the data sanitiser does to every table of a production copy.
 *
 * Each table is kept as it is, purged, or scrubbed column by column. A table that is in none
 * of the three lists makes the sanitiser refuse to run, so a table added later cannot slip
 * through with real data in it. Tables with the `vb_` prefix are the old forum's own tables;
 * the site reads only the two scrubbed below, and every other one is purged.
 *
 * Column rules (`kind` or `kind:source_column`):
 * - `email`, `username`, `name`: a fake value built from the row's key, or from the source column
 * - `password`: the one known test hash
 * - `ip`: a documentation-range address
 * - `clear`: NULL when the column allows it, otherwise an empty string
 * - `blank`: an empty string (for tables that exist only in old copies)
 * - `mask`: "Sanitised" where the column has any content, so empty stays empty
 */
final class SanitiserPolicy
{
    public const LEGACY_PREFIX = 'vb_';

    /** Public content, configuration and structure: carried as they are. */
    public const KEEP = [
        'bug_reports', 'cms_blocks', 'cms_block_versions', 'cms_media_assets', 'cms_navigations',
        'cms_navigation_drafts', 'cms_navigation_draft_variants', 'cms_navigation_versions',
        'cms_pages', 'cms_page_drafts', 'cms_page_draft_variants', 'cms_page_tag', 'cms_page_versions',
        'cms_preview_shares', 'cms_tags', 'contacts', 'cookie_consent_cookies', 'cookie_consent_groups',
        'cookie_policy_versions', 'default_entitlement_grants', 'doc_pages', 'entitlement_definitions',
        'forums', 'forum_groups', 'forum_reads', 'ignored_users', 'membership_plans',
        'membership_plan_prices', 'migrations', 'model_has_permissions', 'model_has_roles',
        'newsletters', 'newsletter_blocks', 'permissions', 'plugins', 'plugin_moderator_notes',
        'plugin_releases', 'plugin_release_scan_finding_reviews', 'plugin_release_security_scans',
        'plugin_release_zc_versions', 'plugin_reports', 'plugin_subscriptions', 'plugin_types',
        'plugin_zc_versions', 'polls', 'poll_options', 'poll_questions', 'poll_votes',
        'poll_vote_answers', 'posts', 'post_attachments', 'post_mentions', 'post_moderation_logs',
        'post_reports', 'privacy_policy_versions', 'private_conversation_participants',
        'private_message_folders', 'reactions', 'registration_email_send_counters', 'roles',
        'role_has_permissions', 'site_notifications', 'threads', 'thread_moderation_logs',
        'thread_reads', 'thread_subscriptions', 'thread_subscription_digest_items',
        'user_entitlement_grants', 'user_notification_preferences', 'user_site_notification_dismissals',
    ];

    /** Operational, personal or secret: removed entirely. */
    public const PURGE = [
        'ai_chat_conversations', 'ai_chat_messages', 'ai_chat_usages', 'ai_embedding_usages', 'audits',
        'billing_event_logs', 'cache', 'cache_locks', 'email_logs', 'failed_jobs', 'jobs', 'job_batches',
        'newsletter_events', 'newsletter_link_clicks', 'newsletter_sends', 'notifications',
        'password_reset_tokens', 'personal_access_tokens', 'privacy_operation_audits', 'privacy_requests',
        'push_subscriptions', 'queue_monitors', 'queue_monitor_failure_groups', 'sessions',
        'spike_post_embeddings', 'subscriptions', 'subscription_items', 'webhook_events',
    ];

    /**
     * Kept for their shape, with the personal columns replaced.
     *
     * @var array<string, array{key: string, columns: array<string, string>}>
     */
    public const SCRUB = [
        'users' => ['key' => 'id', 'columns' => [
            'name' => 'name', 'username' => 'username', 'email' => 'email', 'password' => 'password',
            'stripe_id' => 'clear', 'pm_type' => 'clear', 'pm_last_four' => 'clear',
            'signature' => 'clear', 'biography' => 'clear', 'location' => 'clear', 'interests' => 'clear',
            'occupation' => 'clear', 'ban_reason' => 'clear', 'avatar_path' => 'clear',
            'legacy_password_hash' => 'clear', 'legacy_password_salt' => 'clear',
            'two_factor_secret' => 'clear', 'two_factor_pending_secret' => 'clear',
            'two_factor_recovery_codes' => 'clear', 'two_factor_enabled_at' => 'clear',
            'remember_token' => 'clear',
        ]],
        'legacy_import_user_maps' => ['key' => 'id', 'columns' => [
            'legacy_username' => 'username:legacy_user_id', 'legacy_email' => 'email:legacy_user_id',
            'legacy_ban_reason' => 'clear',
        ]],
        'newsletter_subscribers' => ['key' => 'id', 'columns' => [
            'email' => 'email', 'normalized_email' => 'email', 'name' => 'clear',
            'unsubscribe_reason' => 'clear', 'consent_ip' => 'ip', 'consent_user_agent' => 'clear',
            'metadata' => 'clear',
        ]],
        'private_conversations' => ['key' => 'id', 'columns' => ['subject' => 'mask']],
        'private_messages' => ['key' => 'id', 'columns' => ['body' => 'mask']],
        'user_cookie_consents' => ['key' => 'id', 'columns' => ['ip_hash' => 'clear', 'user_agent_hash' => 'clear']],
        'user_privacy_policy_acceptances' => ['key' => 'id', 'columns' => ['ip_hash' => 'clear', 'user_agent_hash' => 'clear']],
        'user_memberships' => ['key' => 'id', 'columns' => ['provider_subscription_id' => 'clear', 'meta' => 'clear']],
        'entitlement_requests' => ['key' => 'id', 'columns' => ['request_reason' => 'mask', 'review_notes' => 'mask', 'meta' => 'clear']],
        'vb_user' => ['key' => 'userid', 'columns' => [
            'username' => 'username', 'email' => 'email', 'password' => 'blank', 'salt' => 'blank',
            'ipaddress' => 'ip', 'homepage' => 'blank', 'icq' => 'blank', 'aim' => 'blank',
            'yahoo' => 'blank', 'msn' => 'blank', 'skype' => 'blank', 'parentemail' => 'blank',
            'fbuserid' => 'blank', 'fbname' => 'blank', 'fbaccesstoken' => 'blank',
        ]],
        'vb_userfield' => ['key' => 'userid', 'columns' => [
            'temp' => 'blank', 'field1' => 'mask', 'field2' => 'mask', 'field3' => 'mask',
            'field4' => 'mask', 'field5' => 'mask',
        ]],
    ];

    /**
     * Settings whose value is cleared, matched on the key. The rest of the table is configuration.
     */
    public const SECRET_SETTING_PATTERN = 'secret|token|password|passwd|api[_.-]?key|private|credential|dsn|webhook|smtp|email';

    /**
     * Where a table goes: `keep`, `purge`, `scrub` or `settings`; null when the policy does not name it.
     */
    public static function treatmentFor(string $table): ?string
    {
        if (isset(self::SCRUB[$table])) {
            return 'scrub';
        }

        if ($table === 'system_settings') {
            return 'settings';
        }

        if (in_array($table, self::PURGE, true)) {
            return 'purge';
        }

        if (in_array($table, self::KEEP, true)) {
            return 'keep';
        }

        return str_starts_with($table, self::LEGACY_PREFIX) ? 'purge' : null;
    }
}
