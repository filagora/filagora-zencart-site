---
name: verify
description: How to drive this app (filagora-zencart-site) live for runtime verification.
---

# Verifying filagora-zencart-site live

Local dev site: `https://filagora-zencart-site.ddev.site` (DDEV; run `ddev exec php artisan ...`, `ddev exec npm ...` from that repo root). The old `lara-forum` checkout is deprecated: do not verify against it.

Frontend changes need a built bundle: run `ddev exec npm run build` first, or the page will not show new CSS/JS.

## What you can verify without logging in

Guest-facing routes (public forums, threads, CMS pages, plugin listings) can be fetched directly with `curl -sk` against the DDEV site. Pattern:

1. Seed fixture data with `ddev exec php artisan tinker --execute '...'` (real rows, not test fakes). `database/seeders/LocalSeeder.php`, `DemoForumSeeder.php` and `SitePagesSeeder.php` may already cover what you need.
2. `curl -sk` the real route and capture status, headers and body as evidence.
3. Confirm authorization boundaries by seeding a restricted forum or thread and checking the same guest request gets a 403/404.

## Browser-level checks (scroll, JS, layout)

Use the Playwright MCP tools against the DDEV URL for anything JS-driven (modals, scroll behaviour, floating widgets). If the browser fails with a missing webkit executable, see the Playwright memory note for the known fix.

The ADA accessibility widget is injected only in production (`@production` in `resources/views/layouts/main.blade.php`), so it will not appear locally. Judge layout against it from the known position (bottom-left circle) and say so in the report.

## Authenticated flows

Not yet confirmed for this app. Check how login works here before assuming a plain form POST. If it cannot be scripted, verify authenticated flows through Laravel's HTTP test client (`$this->actingAs($user)->post(route(...))`) and state plainly that browser-level verification was not done.
