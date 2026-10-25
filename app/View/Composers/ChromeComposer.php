<?php

namespace App\View\Composers;

use App\Http\Middleware\ApplySearchIconToggleOverride;
use App\Support\LegalPages;
use App\Support\StaffFrontendPreview;
use App\Support\UserImpersonation;
use Filagora\Cms\Services\CmsContent;
use Filagora\Compliance\Services\CookieConsent;
use Filagora\Forums\Services\ActiveAnnouncements;
use Filagora\Forums\Services\AnnouncementDismissals;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * What every page's chrome (the header, footer, cookie banner and site notices) needs, taken from
 * the packages that own it: menus and footer blocks from the CMS, the consent groups and policy
 * from compliance, the notices from the forums announcements. Worked out once per request and
 * again only if who is looking changes (signing in or out, impersonating, toggling the staff
 * preview), then handed to every view.
 */
class ChromeComposer
{
    public function __construct(
        private readonly Request $request,
        private readonly CmsContent $cms,
        private readonly CookieConsent $cookieConsent,
        private readonly LegalPages $legalPages,
        private readonly StaffFrontendPreview $staffPreview,
        private readonly UserImpersonation $impersonation,
        private readonly ActiveAnnouncements $announcements,
        private readonly AnnouncementDismissals $dismissals,
    ) {}

    public function compose(View $view): void
    {
        $request = $this->request;
        $user = $request->user();
        $impersonating = $this->impersonation->active($request);
        $previewEnabled = $request->hasSession()
            ? filter_var($request->session()->get(StaffFrontendPreview::SESSION_KEY, true), FILTER_VALIDATE_BOOL)
            : false;

        $signature = implode(':', [
            $user?->getAuthIdentifier() ?? 'guest',
            $previewEnabled ? 'preview-on' : 'preview-off',
            $impersonating ? 'impersonating' : 'not-impersonating',
            $this->impersonation->originalUserId($request) ?? 'no-original-user',
        ]);

        $shared = $request->attributes->get('frontend.shared_view_data');

        if (! is_array($shared) || $request->attributes->get('frontend.shared_view_data.signature') !== $signature) {
            $shared = $this->build($request, $user, $impersonating);
            $request->attributes->set('frontend.shared_view_data', $shared);
            $request->attributes->set('frontend.shared_view_data.signature', $signature);
        }

        $view->with($shared);
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Request $request, mixed $user, bool $impersonating): array
    {
        $githubBlock = $this->cms->publishedReusableBlock('footer_github');
        $newsletterBlock = $this->cms->publishedReusableBlock('footer_newsletter');
        $githubLogoUrl = $this->cms->mediaUrls(['cms-media/footer-github-logo.png'])['cms-media/footer-github-logo.png'] ?? null;

        $githubHtml = is_array($githubBlock?->data) ? (string) ($githubBlock->data['html'] ?? '') : '';

        if ($githubLogoUrl !== null && $githubHtml !== '') {
            $githubHtml = str_replace('/assets/images/logo/github-logo.png', $githubLogoUrl, $githubHtml);
        }

        return [
            'headerMenuItems' => $this->cms->navigationItems('header'),
            'userMenuItems' => $this->cms->navigationItems('user_menu'),
            'footerUsefulMenuItems' => $this->cms->navigationItems('footer_useful'),
            'footerLegalMenuItems' => $this->cms->navigationItems('footer_legal'),
            'footerGithubBlockHtml' => $githubHtml,
            'footerNewsletterBlockHtml' => is_array($newsletterBlock?->data) ? (string) ($newsletterBlock->data['html'] ?? '') : '',
            'footerGithubLogoUrl' => $githubLogoUrl,
            'cookieConsentWorkflowEnabled' => $this->cookieConsent->isWorkflowEnabled(),
            'searchIconToggleEnabled' => $this->searchIconToggleEnabled($request),
            'cookieConsentGroups' => $this->cookieConsent->enabledGroups(),
            'cookiePolicyPageUrl' => $this->legalPages->hasCookiePolicyPage() ? $this->legalPages->cookiePolicyPageUrl() : null,
            'activeCookiePolicyVersion' => $this->cookieConsent->activePolicyVersion()?->version,
            'staffFrontendPreviewAvailable' => $this->staffPreview->availableFor($user),
            'staffFrontendPreviewEnabled' => $this->staffPreview->enabledFor($request, $user),
            'staffImpersonationActive' => $impersonating,
            'staffImpersonationOriginalUsername' => $this->impersonation->originalUsername($request),
            'activeSiteNotifications' => $this->announcements->forViewer($user),
            'dismissedSiteNotificationIds' => $user ? $this->dismissals->dismissedIdsFor($user) : [],
        ];
    }

    private function searchIconToggleEnabled(Request $request): bool
    {
        if ($request->hasSession() && $request->session()->has(ApplySearchIconToggleOverride::SESSION_KEY)) {
            return (bool) $request->session()->get(ApplySearchIconToggleOverride::SESSION_KEY);
        }

        return (bool) ($request->user()?->search_icon_enabled ?? false);
    }
}
