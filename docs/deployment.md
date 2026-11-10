# Deploying the site

How a release reaches a server. Nothing here names a real host: every server detail is a variable of the
GitHub `production` environment, so the same files serve any install of this site, and are a worked example
for anyone building a site on the Filagora packages (which are ordinary Composer libraries; the steps they add
to any Laravel deploy are listed at the end).

## Layout on the server

    <DEPLOY_PATH>/
      releases/<release-id>/   one directory per deploy, built by the workflow
      shared/.env              the environment file, outside every release
      shared/storage/          uploads, logs, cache, sessions; linked into each release
      shared/build/            built front-end assets, linked into each release
      current -> releases/...  the live release (a symlink, switched atomically)

Nginx serves `<DEPLOY_PATH>/current/public`. The old and new site can run side by side on the same host and
database, each with its own `DEPLOY_PATH` and PHP-FPM pool, and traffic moves by changing which one nginx
serves. Rollback is switching back; the database needs no undoing.

## The workflow

`.github/workflows/deploy.yml` runs **only by hand** (`workflow_dispatch`) and only from `master`. It never runs
on a pull request or a push, because this repository is public and a deploy runs on the server.

- **Package** builds on GitHub's runner (production Composer install, `npm run build`), writes
  `bootstrap/release.json`, and uploads a tarball with a checksum. The artifact is deleted again by a separate
  `cleanup-artifact` job, since an expired artifact keeps its storage until it is deleted.
- **Deploy** runs on a self-hosted runner on the server, in the `production` environment (set required
  reviewers on it), and calls `scripts/deploy/release_deploy.sh`.
- **Rollback** (`action: rollback`, `release_id: <directory name>`) calls `scripts/deploy/rollback_release.sh`.

The deploy job needs a self-hosted runner because it changes the machine it runs on (it extracts into
`DEPLOY_PATH`, repairs permissions, and reloads PHP-FPM and the web server), and GitHub's own runners cannot do
that. Set one up once, as below.

### Variables (environment `production`)

| Variable | Default | Meaning |
| --- | --- | --- |
| `DEPLOY_PATH` | none, required | the directory above |
| `DEPLOY_SHARED_ENV_PATH` | `<DEPLOY_PATH>/shared/.env` | the environment file |
| `DEPLOY_EXPECTED_APP_ENV` | `production` | refuse to deploy if the file's `APP_ENV` differs (`any` to skip) |
| `DEPLOY_PHP_FPM_SERVICE` | `php8.4-fpm` | reloaded after the switch |
| `DEPLOY_WEB_SERVICE` | `nginx` | reloaded after the switch |
| `DEPLOY_WEB_GROUP` | `www-data` | group that must be able to write storage and the bootstrap cache |
| `DEPLOY_KEEP_RELEASES` | `5` | releases kept after a deploy |
| `DEPLOY_SCHEDULER_SERVICE` | empty | systemd unit running `schedule:work`; restarted if set |
| `DEPLOY_QUEUE_WORKER_SERVICES` | empty | comma-separated units; must be active after the deploy |
| `DEPLOY_SEARCH_REINDEX_ARGS` | `--sync-settings` | arguments for `filagora:search:reindex` |
| `DEPLOY_ENV_CHECK` | `warn` | `site:check-env` on the shared `.env` before the switch: `off` skips it, `warn` prints what needs attention, `strict` fails the deploy on it |
| `DEPLOY_EXPECTED_HOSTNAME` | empty | refuse to run unless `hostname` equals this |
| `DEPLOY_FORBIDDEN_ADDRESSES` | empty | comma-separated addresses; refuse to run if the host has any of them (a copy of production keeps its hostname, so use this to keep it from deploying to the real host) |
| `DEPLOY_SMOKE_URLS` | empty | comma-separated URLs (for example `https://<host>/up`) that must answer below 400 after the switch; empty skips the check |
| `DEPLOY_SMOKE_RESOLVE` | empty | curl `--resolve` value (`<host>:443:127.0.0.1`) so the check reaches the origin, not the CDN |

If a smoke check fails, the deploy makes the previous release current again (the same steps as
`rollback_release.sh`), exits non-zero, and prunes nothing. Only code is rolled back: migrations that already ran
stay. A migration in a release must therefore work with the previous release's code: add a column or table in one
release, and remove the old one in a later release.

Package updates arrive as Dependabot pull requests (`.github/dependabot.yml`) that change `composer.lock`. Merging
one changes no live code: deploying is still the manual `Deploy` run.

Two inputs are chosen per run: `run_adopt` and `run_search_reindex`.

## One-time setup

1. **The runner.** In the repository's *Settings, Actions, Runners* add a self-hosted runner on the server and
   give it the label `filagora-production`. Install it as a service under the **deploy user** (a user that can
   write `DEPLOY_PATH`), not root. Register it for this repository alone, never for an organisation-wide group.
   Because the repository is public, also keep *Settings, Actions, General, Fork pull request workflows* on
   "Require approval", and leave the runner's labels off any workflow that runs on a pull request (none does).
2. **Passwordless sudo for the deploy user**, limited to what the scripts run: `chgrp`, `chmod`, `find`,
   `setfacl` and `rm` on paths under `DEPLOY_PATH`, and `systemctl reload` of the PHP-FPM and web services (and
   `restart` of the scheduler unit, if one is set). The script stops with a clear message if `sudo -n` is refused.
3. **The `production` environment.** In *Settings, Environments* create `production`, add required reviewers (so
   a deploy waits for an approval), restrict it to the `master` branch, and set the variables in the table above.
4. **The server.** Create `DEPLOY_PATH/shared/.env` (see below), the PHP-FPM pool and the nginx server block that
   serves `DEPLOY_PATH/current/public`, and the systemd units for the scheduler and queue workers if you use them.
5. **Protect `master`** (required reviews and status checks), since a deploy runs whatever is on it.

## What a deploy does, in order

Extract and checksum, link `.env`, `storage` and the built assets, publish the Filament and Livewire assets,
then (when `run_adopt` is set) `filagora:adopt --dry-run` followed by `filagora:adopt`, then `migrate --force`,
`filagora:doctor`, the config/route/view/event caches, the forums and plugins cache warmers, `about`, the
symlink switch, `queue:restart`, the optional search reindex, `optimize`, **reload PHP-FPM and the web server,
wait, and only then prune old releases**. That last order matters: workers hold the old release's files open
and autoload lazily, so pruning first turns in-flight requests into fatals that never reach the logs.

Adoption is a separate step on purpose. `filagora:adopt` records that the packages' tables already exist (it
changes nothing else); it is for the first deploy onto the live database, not something `migrate` does. Its
dry run fails the deploy on anything it would not adopt.

## The environment file

Create `shared/.env` before the first deploy. Besides the usual database, mail and cache settings it needs:

- **The same `APP_KEY` as the site being replaced**, and the same session driver, cookie name and domain.
  Signed links, encrypted two-factor secrets and signed-in sessions survive the switch only if these match.
- `FILAGORA_SEARCH_ENGINE` (`database` or `meilisearch`, with `MEILISEARCH_HOST` and `MEILISEARCH_KEY`).
- The browser push keys (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`), the same as the old site's,
  or every existing browser subscription stops working. The service worker `/sw.js` must be served as a file
  (`location = /sw.js { try_files $uri =404; }`), not through Laravel.
- Payments and webhooks: `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_WEBHOOK_ID`, `PAYPAL_BASE_URL`, and
  the mail providers' webhook signing keys.
- The version-check cache sync: `PLUGIN_CACHE_SYNC_BASE_URL`, `PLUGIN_CACHE_SYNC_AUTH_TOKEN`,
  `PLUGIN_CACHE_SYNC_ENABLED` (leave it off until the cutover's re-push).

## For a site built on the packages

Any deploy method works (Forge, Envoyer, a script). The steps the packages add to an ordinary Laravel deploy:

1. `composer install --no-dev`, build the assets, publish `filament:assets` and `livewire:publish --assets`.
2. `php artisan migrate --force` for a new database, or `php artisan filagora:adopt` once for one that already
   holds the packages' tables (see each package's README).
3. `php artisan filagora:doctor` fails if the configuration is wrong; run it before switching traffic.
4. With Meilisearch: `php artisan filagora:search:sync-settings`, then `filagora:search:reindex`.
5. Run the scheduler and at least one queue worker; restart workers on every deploy.
