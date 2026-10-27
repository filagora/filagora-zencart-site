<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EmailVerificationPrompt;
use App\Filament\Pages\Auth\Login as UsernameLogin;
use App\Filament\Pages\Auth\Register as FrontendRegister;
use App\Filament\Widgets\EntitlementRequestQueueStats;
use App\Http\Middleware\EnsureBannedDashboardUserIsLoggedOut;
use App\Http\Middleware\EnsureDashboardAccess;
use App\Http\Middleware\ThrottleAuthEndpoints;
use Croustibat\FilamentJobsMonitor\FilamentJobsMonitorPlugin;
use Filagora\Cms\FilagoraCmsPlugin;
use Filagora\Compliance\FilagoraCompliancePlugin;
use Filagora\Compliance\Filament\Widgets\PrivacyKpiOverview;
use Filagora\Core\FilagoraCorePlugin;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filagora\Entitlements\FilagoraEntitlementsPlugin;
use Filagora\Entitlements\Services\AuthorizationService;
use Filagora\Forums\FilagoraForumsPlugin;
use Filagora\Forums\Filament\Widgets\ModerationQueueStats;
use Filagora\Logs\FilagoraLogsPlugin;
use Filagora\Memberships\FilagoraMembershipsPlugin;
use Filagora\Messages\FilagoraMessagesPlugin;
use Filagora\Notifications\NotificationsPlugin;
use Filagora\Plugins\FilagoraPluginsPlugin;
use Filagora\PluginsSecurity\FilagoraPluginsSecurityPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('dashboard')
            ->path(config('site.panel_path'))
            ->login(UsernameLogin::class)
            ->registration(FrontendRegister::class)
            ->emailVerification(EmailVerificationPrompt::class)
            ->passwordReset()
            ->globalSearch(false)
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->plugins([
                FilamentJobsMonitorPlugin::make()
                    ->enableNavigation(fn (): bool => app(AuthorizationService::class)->allows(auth()->user(), 'operator.queue_monitors.view'))
                    ->navigationGroup(fn (): string => app(NavigationGroupRegistry::class)->label('logs'))
                    ->navigationSort(50)
                    ->navigationIcon('heroicon-o-queue-list')
                    ->pluralLabel(fn (): string => __('dashboard.navigation_items.queue_jobs')),
                FilagoraCorePlugin::make(),
                FilagoraCmsPlugin::make(),
                FilagoraEntitlementsPlugin::make(),
                FilagoraForumsPlugin::make(),
                FilagoraLogsPlugin::make(),
                FilagoraCompliancePlugin::make(),
                FilagoraMessagesPlugin::make(),
                FilagoraMembershipsPlugin::make(),
                NotificationsPlugin::make(),
                FilagoraPluginsPlugin::make(),
                FilagoraPluginsSecurityPlugin::make(),
            ])
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                PrivacyKpiOverview::class,
                EntitlementRequestQueueStats::class,
                ModerationQueueStats::class,
            ])
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label(function (): string {
                        $user = auth()->user();

                        if ($user === null) {
                            return __('dashboard.user_menu.profile');
                        }

                        $username = $user->username ?: $user->name ?: __('dashboard.user_menu.profile');

                        return $user->email ? sprintf('%s (%s)', $username, $user->email) : $username;
                    })
                    ->url(fn (): string => route('profile.show')),
                MenuItem::make()
                    ->label(__('dashboard.user_menu.frontend'))
                    ->url(fn (): string => route('home')),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                EnsureBannedDashboardUserIsLoggedOut::class,
                ThrottleAuthEndpoints::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                EnsureDashboardAccess::class,
                Authenticate::class,
            ]);
    }
}
