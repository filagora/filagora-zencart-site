# Filagora Zen Cart site

The Zen Cart support site, built on the [Filagora](https://github.com/filagora) packages and the [Zen Cart profile](https://github.com/filagora/filagora-zencart). A Laravel 13 / Filament 5 application, MIT licensed.

This repository is the application: host code, tests, and the local environment. It does not deploy itself and holds no deploy scripts. Production is deployed by a separate, deployment-only repository (`filagora/filagora-zencart-deploy`) that takes a built release of this one, so nothing here can be changed by a deploy.

It is grown from the generic [`filagora-demo`](https://github.com/filagora/filagora-demo) (a generic demo that knows nothing of this site) and from the existing support site's application code, which is brought across one slice at a time as the packages replace it. The porting plan is kept in the maintainers' private planning repository.

## Run it locally (DDEV)

```bash
ddev start
ddev composer install
cp .env.example .env
ddev exec php artisan key:generate
ddev exec php artisan migrate --seed
ddev exec php artisan test
```

Open <https://filagora-zencart-site.ddev.site/> for the site and <https://filagora-zencart-site.ddev.site/dashboard> for the staff dashboard, and sign in as `admin` / `password` (public, local only: the seeder refuses to run in any other environment).

## Environments

| | Local | Staging | Production |
| --- | --- | --- | --- |
| Where | DDEV | DDEV for now, a sanitised copy of production's data | the existing droplet, beside the old site |
| Data | seeders | a sanitised copy | the existing production database, adopted with `filagora:adopt` |
| Deployed by | n/a | n/a | `filagora-zencart-deploy` |

## Rules that matter

- **The site has no migrations.** Every table belongs to a package or to the profile, so `filagora:adopt` can compare it with production's. A new table or column goes in `filagora-zencart` (`database/migrations/site`) with its snapshot.
- **No secrets, no real data, no deploy scripts in this repository.** CI installs everything from public repositories.
- **Keep the same `APP_KEY`, session driver and cookie settings as the old site** in every environment that shares its data, so signed links, encrypted two-factor secrets and sessions keep working.

## Develop the packages alongside this app

To change a package and see the result here without releasing it, use the local checkouts instead of the Packagist versions. This needs DDEV and a shell that runs bash scripts: Linux, macOS (the scripts stick to the bash 3.2 that macOS ships), or WSL2 on Windows. Native PowerShell or cmd cannot run them.

1. **Clone every package next to this project**, so each sits at `../filagora-<name>` (`core`, `entitlements`, `forums`, `logs`, `cms`, `compliance`, `messages`, `search`, `push`, `memberships`, `notifications`, `plugins`, `plugins-security`, `zencart`). `bin/dev-link` stops and names any that is missing.
2. **Link:** `bin/dev-link`. It restarts DDEV, adds a path repository for each checkout to `composer.json`, and installs them as symlinks, so `vendor/filagora/*` points at your clones. Edit a package and reload the site.
3. **Commit the package changes in the package's own repository**, not here. This site only sees a package's changes once it has a tagged release and the constraint in `composer.json` is raised.
4. **Unlink before committing anything here:** `bin/dev-unlink`. It discards every uncommitted change to `composer.json` and `composer.lock` (so make dependency changes after unlinking, not while linked) and reinstalls the Packagist versions. Never commit those two files while linked.

`bin/dev-link` gives each checkout a fake version taken from its newest git tag (v0.6.9 becomes `0.6.x-dev`), so the packages' own `^0.x` constraints still resolve. If it reports a package has no version tag, run `git fetch --tags` in that checkout.

## Tests

`ddev exec php artisan test` (Pest, on a real MariaDB). CI runs Pint, the migrations, `filagora:doctor` and the suite.

## Contributing and licence

Issues and pull requests are welcome; see [CONTRIBUTING.md](CONTRIBUTING.md) and [SECURITY.md](SECURITY.md). MIT licensed, see [LICENSE](LICENSE). "Zen Cart" and its logos are trademarks of Zen Ventures, LLC; the third-party logos under `public/images` (PayPal, Authorize.net, DigitalOcean and others) belong to their owners and are shown for identification only.
