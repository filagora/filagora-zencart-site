<?php

namespace App\Providers;

use App\Filament\Auth\Responses\LoginResponse as AppLoginResponse;
use App\Filament\Auth\Responses\RegistrationResponse as AppRegistrationResponse;
use App\Support\FormattedContentRenderer;
use App\View\Composers\ChromeComposer;
use App\View\Composers\HeaderComposer;
use Filagora\Core\Content\MarkdownRenderer;
use Filagora\Core\Settings\SettingDefinition;
use Filagora\Core\Settings\SettingsRegistry;
use Filagora\Core\Settings\SettingType;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as FilamentLoginResponse;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as FilamentRegistrationResponse;
use Illuminate\Support\Facades\Blade;
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

        Blade::directive('cmsMarkdown', fn (string $expression): string => "<?php echo \\App\\Support\\CmsMarkdown::block({$expression}); ?>");
        Blade::directive('cmsMarkdownInline', fn (string $expression): string => "<?php echo \\App\\Support\\CmsMarkdown::inline({$expression}); ?>");

        View::composer('*', ChromeComposer::class);
        View::composer('partials.site_header', HeaderComposer::class);
    }
}
