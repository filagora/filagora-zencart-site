<?php

namespace App\Providers;

use App\Filament\Auth\Responses\LoginResponse as AppLoginResponse;
use App\Filament\Auth\Responses\RegistrationResponse as AppRegistrationResponse;
use App\Markdown\BlankLineGapExtension;
use App\Markdown\ZenCartHtmlTransformer;
use App\Models\BugReport;
use App\Observers\FlushForumsIndexCacheObserver;
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
use Filagora\Forums\Contracts\StaffPreview;
use Filagora\Forums\Events\ForumStatsChanged;
use Filagora\Forums\Models\Post;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Support\Models;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as FilamentLoginResponse;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as FilamentRegistrationResponse;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
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
        Thread::resolveRelationUsing('poll', fn ($thread) => $thread->hasOne(Models::class('poll'), 'thread_id'));
        Thread::resolveRelationUsing('bugReports', fn ($thread) => $thread->hasMany(BugReport::class, 'thread_id'));
    }

    /**
     * The forum index and the search bar's forum list are cached; any change to a group, forum,
     * thread or post (and the counter updates that skip model events) drops them.
     */
    private function registerForumCacheFlush(): void
    {
        foreach (['forum_group', 'forum', 'thread', 'post'] as $key) {
            Models::class($key)::observe(FlushForumsIndexCacheObserver::class);
        }

        Event::listen(ForumStatsChanged::class, fn () => ForumsIndexCache::flushAll());
    }
}
