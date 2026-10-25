# Contributing

Thanks for helping. Open an issue to discuss anything large, and send changes as pull requests;
they are squash-merged once CI is green.

## Local setup

See the README: DDEV, `composer install`, `php artisan migrate --seed`, `php artisan test`.

## Expectations

- Every behaviour change ships with tests, and every bug fix with a regression test that fails
  before the fix.
- Run `vendor/bin/pint` before pushing; CI checks it.
- Host migrations that touch a table production already has must do nothing there (create or add
  only if missing). The README explains why.
- Do not add private hostnames, IP addresses, personal email addresses, keys or real user data to
  code, docs or test fixtures. CI runs a leak scan; use reserved example domains and the
  documentation IP ranges in tests.
- Sign the [Zen Cart Contributor License Agreement](public/cla.txt) terms by opening your first
  pull request: submitting a contribution means you accept them.
