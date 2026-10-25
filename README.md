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

- **Host migrations that touch a table production already has must be written to do nothing there** (add a column only if it is missing). Production's tables are the truth; see `database/migrations/*_add_site_columns_to_users_table.php`.
- **No secrets, no real data, no deploy scripts in this repository.** CI installs everything from public repositories.
- **Keep the same `APP_KEY`, session driver and cookie settings as the old site** in every environment that shares its data, so signed links, encrypted two-factor secrets and sessions keep working.

## Develop the packages alongside this app

Clone the package repositories next to this one and run `bin/dev-link` (and `bin/dev-unlink` to undo it), exactly as in the demo.

## Tests

`ddev exec php artisan test` (Pest, on a real MariaDB). CI runs Pint, the migrations, `filagora:doctor` and the suite.

## Contributing and licence

Issues and pull requests are welcome; see [CONTRIBUTING.md](CONTRIBUTING.md) and [SECURITY.md](SECURITY.md). MIT licensed, see [LICENSE](LICENSE). "Zen Cart" and its logos are trademarks of Zen Ventures, LLC; the third-party logos under `public/images` (PayPal, Authorize.net, DigitalOcean and others) belong to their owners and are shown for identification only.
