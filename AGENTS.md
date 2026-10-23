# AGENTS

Guidance for AI coding agents working in `filagora-zencart-site`. `CLAUDE.md` imports this file; edit here.

## What this is

The Zen Cart support site: a Laravel 13 / Filament 5 application built on the public Filagora packages and the private Zen Cart profile. **This repository is private**, so it may name the origin site and its infrastructure, but it must never hold a secret, real personal data, or a deploy script.

It replaces the old support site on the same production database. It starts as the generic `filagora-demo`'s shape and takes in the old site's application code slice by slice, deleting each piece the packages replace. The plan (private planning repository, `zencart-site-plan.md`) is the authority for order and decisions.

## Rules

- **Production's database is the truth.** Packages and the profile bring their own migrations and `filagora:adopt` accepts the live tables. Host migrations touching a table production already has must do nothing there (add a column only if missing). Never rename or reshape a production table to tidy it.
- **No deployment here.** Deploy scripts, the deploy workflow, server configuration and cutover runbooks live in the separate `filagora-zencart-deploy` repository, which deploys a built release of this one. This repository builds and tags releases; it never deploys them.
- **Nothing in a deploy may change the dashboard's behaviour.** Behaviour comes from a release plus the host's `.env`; keep application code, config and migrations here and nothing application-shaped in the deploy repository.
- **The generic demo must stay free of this site.** Do not copy anything site-specific back into `filagora-demo` or the packages; Zen Cart specifics belong in the profile (`filagora-zencart`) behind a package contract, or here.
- **Same `APP_KEY`, session and cookie settings as the old site** wherever data is shared.
- **Never commit** `.env`, `auth.json`, a token, a database dump or sanitised data.
- Every behaviour change ships with tests; a bug fix with a regression test that fails first.

## Commands

Run everything through DDEV (`ddev start` once). Composer needs read access to the private profile: see the README.

```bash
ddev composer install
ddev exec php artisan migrate --seed
ddev exec php artisan test
ddev exec vendor/bin/pint        # fixes; `--test` only checks
```

Tests always run against MariaDB (database `testing`), never SQLite.
