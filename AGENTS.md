# AGENTS

Guidance for AI coding agents working in `filagora-zencart-site`. `CLAUDE.md` imports this file; edit here.

## What this is

The Zen Cart support site: a Laravel 13 / Filament 5 application built on the public Filagora packages and the Zen Cart profile. **This repository is public** (MIT), so it must never hold a secret, real personal data, or a host-specific detail: every server name, address and path comes from the deploy environment's variables.

It replaces the old support site on the same production database. It starts as the generic `filagora-demo`'s shape and takes in the old site's application code slice by slice, deleting each piece the packages replace. The plan (private planning repository, `zencart-site-plan.md`) is the authority for order and decisions.

## Rules

- **Production's database is the truth, and the site has no migrations.** Every table belongs to a package or to the profile (`filagora-zencart`, `database/migrations/site`), and `filagora:adopt` compares them all with the live tables. A new table or column goes there, with its snapshot; `database/migrations` here stays empty. Never rename or reshape a production table to tidy it.
- **Deploy machinery lives here, host details do not.** `scripts/deploy/`, the manual-only `Deploy` workflow and `docs/deployment.md` are in this repository (decision 2026-11-01: no separate deploy repository). Nginx, fail2ban, nftables, Cloudflare and the cutover runbook with real names stay private. For now the deploy targets the new host, never the live site: keep `DEPLOY_FORBIDDEN_ADDRESSES` set to the live host's address.
- **Nothing in a deploy may change the dashboard's behaviour.** Behaviour comes from a release plus the host's `.env`; keep application code, config and migrations here and nothing application-shaped in the deploy repository.
- **The generic demo must stay free of this site.** Do not copy anything site-specific back into `filagora-demo` or the packages; Zen Cart specifics belong in the profile (`filagora-zencart`) behind a package contract, or here.
- **Same `APP_KEY`, session and cookie settings as the old site** wherever data is shared.
- **Never commit** `.env`, `auth.json`, a token, a database dump or sanitised data.
- Every behaviour change ships with tests; a bug fix with a regression test that fails first.

## Commands

Run everything through DDEV (`ddev start` once). See the README for Composer setup.

```bash
ddev composer install
ddev exec php artisan migrate --seed
ddev exec php artisan test
ddev exec vendor/bin/pint        # fixes; `--test` only checks
```

Tests always run against MariaDB (database `testing`), never SQLite.
