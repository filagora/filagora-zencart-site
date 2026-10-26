<?php

namespace App\Models;

use App\Models\Concerns\HasSiteAccount;
use App\TimeFormat;
use Database\Factories\UserFactory;
use Filagora\Entitlements\Concerns\HasEntitlements;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Support\Models;
use Filagora\Memberships\Concerns\HasMemberships;
use Filagora\Notifications\Concerns\HasNotificationPreferences;
use Filagora\Plugins\Models\Plugin;
use Filagora\Plugins\Support\Models as PluginModels;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasEntitlements, HasFactory, HasMemberships, HasNotificationPreferences, HasSiteAccount, Notifiable;

    /**
     * The columns of the site's users table that may be filled in bulk, as the site this one
     * replaces had them. Anything not listed here is set explicitly.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'legacy_password_hash',
        'legacy_password_salt',
        'legacy_password_algo',
        'legacy_password_imported_at',
        'legacy_registered_at',
        'forum_read_baseline_at',
        'avatar_path',
        'membership_expires_at',
        'timezone',
        'time_format',
        'signature',
        'signature_format',
        'biography',
        'location',
        'interests',
        'occupation',
        'user_title',
        'is_banned',
        'ban_reason',
        'banned_at',
        'banned_until',
        'thread_subscription_email_frequency',
        'allow_private_messages',
        'always_moderate_forum_content',
        'moderate_first_forum_post',
        'search_icon_enabled',
        'show_archived_plugin_releases',
        'prefers_rich_editor',
        'auto_subscribe_on_post',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'legacy_password_hash',
        'legacy_password_salt',
        'legacy_password_algo',
        'remember_token',
        'two_factor_secret',
        'two_factor_pending_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * The plugins this account follows.
     *
     * @return BelongsToMany<Plugin, $this>
     */
    public function subscribedPlugins(): BelongsToMany
    {
        return $this->belongsToMany(
            PluginModels::class('plugin'),
            (new (PluginModels::class('subscription')))->getTable(),
            'user_id',
            'plugin_id',
        )->withTimestamps();
    }

    /**
     * The threads this account follows.
     *
     * @return BelongsToMany<Thread, $this>
     */
    public function subscribedThreads(): BelongsToMany
    {
        return $this->belongsToMany(
            Models::class('thread'),
            (new (Models::class('thread_subscription')))->getTable(),
            'user_id',
            'thread_id',
        )->withTimestamps();
    }

    /**
     * The dashboard is for staff: the site's `operator.dashboard.access` entitlement opens the
     * door, and the individual `operator.*` entitlements decide what is inside it.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasEntitlement('operator.dashboard.access');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'membership_expires_at' => 'datetime',
            'password' => 'hashed',
            'legacy_password_imported_at' => 'datetime',
            'legacy_registered_at' => 'datetime',
            'forum_read_baseline_at' => 'datetime',
            'two_factor_enabled_at' => 'datetime',
            'two_factor_recovery_codes' => 'encrypted:array',
            'is_banned' => 'boolean',
            'banned_at' => 'datetime',
            'banned_until' => 'datetime',
            'allow_private_messages' => 'boolean',
            'always_moderate_forum_content' => 'boolean',
            'moderate_first_forum_post' => 'boolean',
            'search_icon_enabled' => 'boolean',
            'show_archived_plugin_releases' => 'boolean',
            'prefers_rich_editor' => 'boolean',
            'auto_subscribe_on_post' => 'boolean',
            'last_seen_at' => 'datetime',
            'forum_posts_count' => 'integer',
            'time_format' => TimeFormat::class,
        ];
    }
}
