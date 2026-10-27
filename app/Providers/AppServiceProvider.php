<?php

namespace App\Providers;

use App\Filament\Auth\Responses\LoginResponse as AppLoginResponse;
use App\Filament\Auth\Responses\RegistrationResponse as AppRegistrationResponse;
use App\Markdown\BlankLineGapExtension;
use App\Markdown\ZenCartHtmlTransformer;
use App\Models\BugReport;
use App\Models\User;
use App\Notifications\BellListeners;
use App\Observers\BugReportObserver;
use App\Observers\BumpPluginsIndexCacheObserver;
use App\Observers\FlushForumsIndexCacheObserver;
use App\Observers\SyncUserPostCountObserver;
use App\Policies\BugReportPolicy;
use App\Policies\ForumPostPolicy;
use App\Policies\ForumThreadPolicy;
use App\Policies\PluginPolicy;
use App\Policies\PluginReleasePolicy;
use App\Services\UserPostCountService;
use App\Support\FormattedContentRenderer;
use App\Support\ForumsIndexCache;
use App\Support\ForumStaffPreview;
use App\View\Composers\ChromeComposer;
use App\View\Composers\ForumPagesComposer;
use App\View\Composers\HeaderComposer;
use App\View\Composers\ModerationQueueComposer;
use Filagora\Core\Content\ExtensionRegistry;
use Filagora\Core\Content\MarkdownRenderer;
use Filagora\Core\Settings\SettingDefinition;
use Filagora\Core\Settings\SettingsRegistry;
use Filagora\Core\Settings\SettingType;
use Filagora\Entitlements\Services\AuthorizationService;
use Filagora\Forums\Contracts\StaffPreview;
use Filagora\Forums\Events\ForumStatsChanged;
use Filagora\Forums\Events\PostModerated;
use Filagora\Forums\Events\PostReported;
use Filagora\Forums\Events\SubscribersToNotify;
use Filagora\Forums\Events\ThreadDeleted;
use Filagora\Forums\Events\ThreadModerated;
use Filagora\Forums\Events\UsersMentioned;
use Filagora\Forums\Models\Post;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Support\Models;
use Filagora\Messages\Events\MessageReceived;
use Filagora\Plugins\Events\ReleaseRejected;
use Filagora\Plugins\Events\SubscribersToNotify as PluginSubscribersToNotify;
use Filagora\Plugins\Models\Plugin as PluginModel;
use Filagora\Plugins\Models\PluginRelease as PluginReleaseModel;
use Filagora\Plugins\Support\Models as PluginModels;
use Filagora\ZenCart\Models\ZcVersion;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as FilamentLoginResponse;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as FilamentRegistrationResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FilamentLoginResponse::class, AppLoginResponse::class);
        $this->app->bind(FilamentRegistrationResponse::class, AppRegistrationResponse::class);

        // The forum package's "view as a member" switch follows the staff member's session toggle.
        $this->app->bind(StaffPreview::class, ForumStaffPreview::class);

        // Rendered post and notice HTML is reused instead of re-parsed on every view.
        $this->app->singleton(FormattedContentRenderer::class, fn ($app): FormattedContentRenderer => new FormattedContentRenderer(
            $app->make(MarkdownRenderer::class),
            $app->make('cache')->store(),
            (int) $app['config']->get('site.formatted_content_cache_ttl_seconds', 86400),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Settings the site itself reads (the packages declare their own). Production already holds
        // the value; this gives the dashboard its label and the code its type.
        $this->callAfterResolving(SettingsRegistry::class, function (SettingsRegistry $settings): void {
            $settings->define(new SettingDefinition(
                key: 'moderate_first_post_for_new_users',
                type: SettingType::Boolean,
                default: true,
                label: 'Moderate First Post For New Users',
                description: 'When enabled, newly registered users have their first forum thread or reply submitted for staff approval.',
            ));
        });

        // The site's additions to markdown rendering: blank-line gaps, the old forum's tags, issue
        // and mention links.
        $this->callAfterResolving(ExtensionRegistry::class, function (ExtensionRegistry $registry): void {
            $registry->extension(new BlankLineGapExtension);
            $registry->transformer(new ZenCartHtmlTransformer);
        });

        Blade::directive('cmsMarkdown', fn (string $expression): string => "<?php echo \\App\\Support\\CmsMarkdown::block({$expression}); ?>");
        Blade::directive('cmsMarkdownInline', fn (string $expression): string => "<?php echo \\App\\Support\\CmsMarkdown::inline({$expression}); ?>");

        Gate::policy(Post::class, ForumPostPolicy::class);
        Gate::policy(BugReport::class, BugReportPolicy::class);
        BugReport::observe(BugReportObserver::class);

        // Staff acting as another member: needs the "act as" entitlement, never as themselves, and
        // never as an operator they could not themselves make.
        Gate::define('impersonate', function (User $actor, User $target): bool {
            $access = app(AuthorizationService::class);

            return $access->allows($actor, 'operator.users.act_as')
                && ! $actor->is($target)
                && ($access->allows($actor, 'operator.users.assign_operator_access')
                    || ! $access->allowsAny($target, ['operator.users.assign_operator_access', 'operator.system.settings.manage']));
        });
        Gate::policy(Thread::class, ForumThreadPolicy::class);
        Gate::policy(PluginModel::class, PluginPolicy::class);
        Gate::policy(PluginReleaseModel::class, PluginReleasePolicy::class);

        $this->registerRateLimiters();
        $this->registerForumRelations();
        $this->registerForumCacheFlush();

        View::composer(['forum.*', 'thread.*', 'post.*'], ForumPagesComposer::class);
        View::composer('forum.partials.moderation_queue', ModerationQueueComposer::class);
        View::composer('*', ChromeComposer::class);
        View::composer('partials.site_header', HeaderComposer::class);
    }

    /**
     * Relations the public forum pages use that the forums package does not define: a post's
     * attachments and reactions, a thread's poll and bug reports. Registered on the package's base
     * models, which the site's own subclasses inherit.
     */
    private function registerForumRelations(): void
    {
        Post::resolveRelationUsing('attachments', fn ($post) => $post->hasMany(Models::class('post_attachment'), 'post_id'));
        Post::resolveRelationUsing('reactions', fn ($post) => $post->hasMany(Models::class('reaction'), 'post_id'));
        Thread::resolveRelationUsing('subscribedUsers', fn ($thread) => $thread->belongsToMany(User::class, (new (Models::class('thread_subscription')))->getTable(), 'thread_id', 'user_id')->withTimestamps());
        PluginReleaseModel::resolveRelationUsing('zcVersions', fn ($release) => $release->belongsToMany(ZcVersion::class, (new ZcVersion)->releasesTable(), 'plugin_release_id', 'plugin_zc_version_id')->withTimestamps());
        PluginModel::resolveRelationUsing('forumThread', fn ($plugin) => $plugin->belongsTo(Models::class('thread'), 'forum_thread_id'));
        Thread::resolveRelationUsing('poll', fn ($thread) => $thread->hasOne(Models::class('poll'), 'thread_id'));
        Thread::resolveRelationUsing('bugReports', fn ($thread) => $thread->hasMany(BugReport::class, 'thread_id'));
    }

    /**
     * The forum index and the search bar's forum list are cached; any change to a group, forum,
     * thread or post (and the counter updates that skip model events) drops them.
     */
    private function registerForumCacheFlush(): void
    {
        foreach (['type', 'plugin', 'release'] as $key) {
            PluginModels::class($key)::observe(BumpPluginsIndexCacheObserver::class);
        }

        ZcVersion::observe(BumpPluginsIndexCacheObserver::class);

        foreach (['forum_group', 'forum', 'thread', 'post'] as $key) {
            Models::class($key)::observe(FlushForumsIndexCacheObserver::class);
        }

        // The author post-count badge on the thread page is a column kept in step with every post.
        Models::class('post')::observe(SyncUserPostCountObserver::class);

        // A deleted thread takes its posts with a database cascade, which fires no model events, so
        // the authors' badge counters are rebuilt from the posts that are left.
        Event::listen(ThreadDeleted::class, fn (ThreadDeleted $event) => app(UserPostCountService::class)->syncForUserIds($event->postAuthorIds));

        Event::listen(ForumStatsChanged::class, fn () => ForumsIndexCache::flushAll());

        // The in-app side of what the packages announce (they send the mail).
        Event::listen(SubscribersToNotify::class, [BellListeners::class, 'threadUpdated']);
        Event::listen(UsersMentioned::class, [BellListeners::class, 'mentioned']);
        Event::listen(PostModerated::class, [BellListeners::class, 'postModerated']);
        Event::listen(ThreadModerated::class, [BellListeners::class, 'threadModerated']);
        Event::listen(PostReported::class, [BellListeners::class, 'postReported']);
        Event::listen(MessageReceived::class, [BellListeners::class, 'messageReceived']);
        Event::listen(PluginSubscribersToNotify::class, [BellListeners::class, 'pluginUpdated']);
        Event::listen(ReleaseRejected::class, [BellListeners::class, 'releaseRejected']);
    }

    /**
     * Per-minute limits keyed by the signed-in user, or the address for a guest.
     */
    private function registerRateLimiters(): void
    {
        foreach ([
            'markdown-preview' => 30,
            'message-user-search' => 30,
            'notifications' => 120,
            'push-subscription' => 20,
            'moderation-action' => 60,
        ] as $name => $perMinute) {
            RateLimiter::for($name, fn (Request $request): Limit => Limit::perMinute($perMinute)->by(
                $request->user()?->id !== null ? 'user:'.$request->user()->id : 'ip:'.$request->ip(),
            ));
        }

        RateLimiter::for('privacy-request-submit', function (Request $request): array {
            $key = $request->user()?->id !== null ? 'user:'.$request->user()->id : 'ip:'.$request->ip();

            return [
                Limit::perHour(3)->by('privacy-request-submit:hour:'.$key),
                Limit::perDay(10)->by('privacy-request-submit:day:'.$key),
            ];
        });
    }
}
