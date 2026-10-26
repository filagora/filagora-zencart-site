import './modules/site-header';
import './modules/form-submit-lock';
import './modules/cookie-consent';
import './modules/site-notifications';
import './modules/push';

// Page-specific modules are added here, with the DOM marker that decides whether to fetch them,
// as the slice that owns the page is ported (see zencart-site-frontend-plan.md in the planning
// repository). Each is only downloaded on pages that carry its marker.
const pageModules = [
    ['#report-post-modal', () => import('./modules/thread-show')],
    ['[data-relocate-dialog]', () => import('./modules/moderation-relocate')],
    ['[data-thread-delete-dialog]', () => import('./modules/thread-delete-dialog')],
    ['[data-report-resolve-form]', () => import('./modules/report-resolve')],
    ['[data-markdown-trigger]', () => import('./modules/markdown-paste')],
    ['[data-markdown-trigger]', () => import('./modules/markdown-toolbar')],
    ['[data-poll-builder]', () => import('./modules/poll-builder')],
    ['[data-thread-attachments-popup]', () => import('./modules/forum-thread-list')],
    ['[data-forum-search-bar]', () => import('./modules/forum-search-bar')],
    ['[data-home-motion-root]', () => import('./modules/home-motion')],
    ['#deploySteps', () => import('./modules/home-deploy-steps')],
];

pageModules.forEach(([selector, importer]) => {
    if (document.querySelector(selector)) {
        importer();
    }
});
