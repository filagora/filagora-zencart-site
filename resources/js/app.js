import './modules/site-header';
import './modules/form-submit-lock';
import './modules/cookie-consent';
import './modules/site-notifications';
import './modules/push';

// Page-specific modules are added here, with the DOM marker that decides whether to fetch them,
// as the slice that owns the page is ported (see zencart-site-frontend-plan.md in the planning
// repository). Each is only downloaded on pages that carry its marker.
const pageModules = [];

pageModules.forEach(([selector, importer]) => {
    if (document.querySelector(selector)) {
        importer();
    }
});
