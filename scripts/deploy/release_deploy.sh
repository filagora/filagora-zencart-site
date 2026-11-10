#!/usr/bin/env bash

set -euo pipefail

require_env() {
  local name="$1"
  if [ -z "${!name:-}" ]; then
    echo "Missing required environment variable: ${name}" >&2
    exit 1
  fi
}

read_dotenv_value() {
  local env_path="$1"
  local key="$2"

  if [ ! -f "${env_path}" ]; then
    return 1
  fi

  php -r '
    $path = $argv[1];
    $target = $argv[2];
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        exit(1);
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === "" || str_starts_with($trimmed, "#")) {
            continue;
        }

        if (! str_contains($trimmed, "=")) {
            continue;
        }

        [$name, $value] = explode("=", $trimmed, 2);
        if (trim($name) !== $target) {
            continue;
        }

        $value = trim($value);
        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];
            if (($first === "\"" && $last === "\"") || ($first === "'"'"'" && $last === "'"'"'")) {
                $value = substr($value, 1, -1);
            }
        }

        echo $value;
        exit(0);
    }

    exit(1);
  ' "${env_path}" "${key}" 2>/dev/null || true
}

dotenv_truthy() {
  local value
  value="$(read_dotenv_value "$1" "$2")"
  case "${value,,}" in
    1|true|yes|on)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

run_privileged() {
  if command -v sudo >/dev/null 2>&1; then
    local sudo_stderr
    sudo_stderr="$(mktemp)"

    if sudo -n "$@" 2>"${sudo_stderr}"; then
      rm -f "${sudo_stderr}"
      return
    fi

    if grep -Eq 'a password is required|a terminal is required|not allowed to execute' "${sudo_stderr}"; then
      rm -f "${sudo_stderr}"
      echo "Passwordless sudo is required for deploy user $(id -un) to run: $*" >&2
      echo "Configure NOPASSWD sudo for deploy-time permission repair and service restarts, or run the deploy as a user that already has those privileges." >&2
      exit 1
    fi

    cat "${sudo_stderr}" >&2
    rm -f "${sudo_stderr}"
    exit 1
  fi

  if "$@" >/dev/null 2>&1; then
    return
  fi

  echo "Command requires privileges but sudo is unavailable: $*" >&2
  exit 1
}

check_disk_space() {
  local path="$1"
  local min_free_mb="$2"
  local free_mb

  free_mb="$(df -Pm "${path}" | awk 'NR==2 {print $4}')"

  if [ "${free_mb}" -lt "${min_free_mb}" ]; then
    echo "Deployment aborted: only ${free_mb}MB free at ${path}; ${min_free_mb}MB required." >&2
    exit 1
  fi
}

assert_group_can_write() {
  local path="$1"
  local group_name="$2"
  if ! find "$path" -maxdepth 0 -group "$group_name" -perm -g=w | grep -q .; then
    echo "Path is not writable by group ${group_name}: ${path}" >&2
    exit 1
  fi
}

apply_group_permissions_if_owned() {
  local base_path="$1"
  local group_name="$2"
  local current_user
  current_user="$(id -un)"

  # Best-effort: this pass only touches paths the deploy user already owns, so
  # the run_privileged pass in normalize_runtime_permissions is the authority
  # for everything else. shared/storage routinely contains subtrees the web
  # user (www-data) created and the deploy user cannot even traverse
  # (e.g. attachments/uploads/YYYY/MM), which makes find exit non-zero. Under
  # `set -e` that aborted the whole deploy *before* the current-symlink swap,
  # so a benign permission notice silently blocked releases. Swallow it here;
  # the privileged pass still runs unguarded and assert_group_can_write still
  # fails the deploy if the tree really is not group-writable afterwards.
  find "${base_path}" -user "${current_user}" -type d -exec chmod 775 {} + 2>/dev/null || true
  find "${base_path}" -user "${current_user}" -type f -exec chmod 664 {} + 2>/dev/null || true
  find "${base_path}" -user "${current_user}" -exec chgrp "${group_name}" {} + 2>/dev/null || true
}

normalize_runtime_permissions() {
  local path="$1"
  local group_name="$2"

  mkdir -p "${path}"
  apply_group_permissions_if_owned "${path}" "${group_name}"
  run_privileged chgrp -R "${group_name}" "${path}"
  # "{} +" batches paths into as few chmod invocations as the argument limit
  # allows. With "{} \;" find forks one chmod per file, and this runs over
  # shared/storage — attachments, one session file per visitor, the file
  # cache — so it was spawning a process per file and burning a CPU core for
  # minutes on a two-core host mid-deploy.
  run_privileged find "${path}" -type d -exec chmod 2775 {} +
  run_privileged find "${path}" -type f -exec chmod 664 {} +

  if command -v setfacl >/dev/null 2>&1; then
    run_privileged setfacl -R -m "g:${group_name}:rwx" "${path}"
    run_privileged setfacl -R -d -m "g:${group_name}:rwx" "${path}"
  fi
}

assert_default_group_acl() {
  local path="$1"
  local group_name="$2"

  if ! command -v setfacl >/dev/null 2>&1; then
    return
  fi

  if ! command -v getfacl >/dev/null 2>&1; then
    echo "Default ACL verification requires getfacl when setfacl is available: ${path}" >&2
    exit 1
  fi

  local acl_output
  acl_output="$(getfacl -cp "${path}")"

  # Here-strings, not "printf | grep -q": grep -q exits at its first match, the
  # still-writing printf takes SIGPIPE, and under pipefail that turns a
  # successful match into a failed pipeline (a false "ACL is missing").
  if ! grep -Eq "^default:group(:${group_name})?:rwx$" <<<"${acl_output}"; then
    echo "Default ACL is missing rwx access for group ${group_name} on ${path}" >&2
    exit 1
  fi

  if grep -Eq '^default:mask::' <<<"${acl_output}" && ! grep -Eq '^default:mask::rwx$' <<<"${acl_output}"; then
    echo "Default ACL mask does not preserve group rwx access on ${path}" >&2
    exit 1
  fi
}

assert_queue_workers_active() {
  local services_csv="$1"
  local retries="${DEPLOY_QUEUE_WORKER_CHECK_RETRIES:-5}"
  local interval="${DEPLOY_QUEUE_WORKER_CHECK_INTERVAL_SECONDS:-2}"
  local failures=()
  local unit
  local status
  local attempt

  IFS=',' read -ra units <<<"${services_csv}"

  for unit in "${units[@]}"; do
    unit="$(echo "${unit}" | xargs)"

    if [ -z "${unit}" ]; then
      continue
    fi

    # queue:restart is a soft signal — the old worker exits after its
    # current job/poll cycle and systemd's Restart=always brings a fresh one
    # up, which briefly reports "activating" rather than "active" while PHP
    # boots. Retry for a few seconds before treating that as a real failure,
    # rather than failing on a normal transient state.
    for attempt in $(seq 1 "${retries}"); do
      status="$(systemctl is-active "${unit}" 2>/dev/null)" || true

      if [ "${status}" = "active" ]; then
        break
      fi

      if [ "${attempt}" -lt "${retries}" ]; then
        sleep "${interval}"
      fi
    done

    if [ "${status}" != "active" ]; then
      failures+=("${unit} (${status})")
    fi
  done

  if [ "${#failures[@]}" -gt 0 ]; then
    echo "Deployment aborted: queue worker service(s) not active after deploy:" >&2
    for failure in "${failures[@]}"; do
      echo "  - ${failure}" >&2
    done
    echo "A code deploy alone does not restart a running queue worker; without a live worker, dispatched jobs (e.g. the Backups dashboard's \"Run Backup Now\") silently never execute. If a unit shows \"not-found\", it was never created on this server — see docs/deployment.md. If it shows \"failed\"/\"inactive\", check \`systemctl status <unit>\` and \`journalctl -u <unit>\` for why it didn't come back up after \`queue:restart\`." >&2
    exit 1
  fi
}

publish_release_build_assets() {
  local release_public_build_dir="$1"
  local shared_build_dir="$2"
  local release_manifest_path="${release_public_build_dir}/manifest.json"
  local release_assets_dir="${release_public_build_dir}/assets"
  local shared_assets_dir="${shared_build_dir}/assets"

  if [ ! -f "${release_manifest_path}" ]; then
    echo "Built asset manifest not found at ${release_manifest_path}" >&2
    exit 1
  fi

  if [ ! -d "${release_assets_dir}" ]; then
    echo "Built asset directory not found at ${release_assets_dir}" >&2
    exit 1
  fi

  mkdir -p "${shared_assets_dir}"
  cp -R "${release_assets_dir}/." "${shared_assets_dir}/"
  cp "${release_manifest_path}" "${shared_build_dir}/manifest.json"

  find "${shared_build_dir}" -type d -exec chmod 755 {} +
  find "${shared_build_dir}" -type f -exec chmod 644 {} +
}

# awk must be the last stage: a downstream `grep -q` exits on the first match,
# the writer takes SIGPIPE (141), and under `pipefail` the whole check reads as
# "command absent" — which is how filament:assets went unpublished for a week
# in Sep 2026 with a green deploy.
artisan_command_exists() {
  php artisan list --raw | awk -v command="$1" '$1 == command { found = 1 } END { exit found ? 0 : 1 }'
}

assert_file_exists() {
  if [ ! -f "$1" ]; then
    echo "Expected file missing after $2: $1" >&2
    exit 1
  fi
}

# Refuses to run on a machine the deploy was not meant for; runs before anything is touched.
assert_deploy_target() {
  local actual_hostname
  local addresses
  local forbidden
  local address

  if [ -n "${DEPLOY_EXPECTED_HOSTNAME}" ]; then
    actual_hostname="$(hostname)"

    if [ "${actual_hostname}" != "${DEPLOY_EXPECTED_HOSTNAME}" ]; then
      echo "Refusing to deploy: this host is ${actual_hostname}, expected ${DEPLOY_EXPECTED_HOSTNAME}." >&2
      exit 1
    fi
  fi

  if [ -n "${DEPLOY_FORBIDDEN_ADDRESSES}" ]; then
    addresses=" $(hostname -I 2>/dev/null || true) "

    IFS=',' read -ra forbidden <<<"${DEPLOY_FORBIDDEN_ADDRESSES}"

    for address in "${forbidden[@]}"; do
      address="$(echo "${address}" | xargs)"

      if [ -n "${address}" ] && [[ "${addresses}" == *" ${address} "* ]]; then
        echo "Refusing to deploy: this host has the forbidden address ${address}." >&2
        exit 1
      fi
    done
  fi
}

# Runs from the new release directory, before the switch. site:check-env exits non-zero when the file has
# a renamed, unread or unknown key; in `warn` mode that is printed and the deploy goes on.
check_environment_file() {
  case "${DEPLOY_ENV_CHECK}" in
    off)
      return
      ;;
    warn | strict)
      if php artisan site:check-env "${DEPLOY_SHARED_ENV_PATH}"; then
        return
      fi

      if [ "${DEPLOY_ENV_CHECK}" = "strict" ]; then
        echo "Environment check failed for ${DEPLOY_SHARED_ENV_PATH}; refusing to switch." >&2
        exit 1
      fi

      echo "Warning: environment check found keys that need attention in ${DEPLOY_SHARED_ENV_PATH}; continuing deploy." >&2
      ;;
    *)
      echo "DEPLOY_ENV_CHECK must be off, warn or strict (got: ${DEPLOY_ENV_CHECK})" >&2
      exit 1
      ;;
  esac
}

# Succeeds when every URL answers below 400 within the retries; a reload leaves a moment where workers are
# still coming up, so each URL gets a few attempts before it counts as failed.
smoke_check_urls() {
  local urls_csv="$1"
  local retries="${DEPLOY_SMOKE_RETRIES}"
  local interval="${DEPLOY_SMOKE_INTERVAL_SECONDS}"
  local resolve_args=()
  local url
  local attempt
  local ok

  if [ -n "${DEPLOY_SMOKE_RESOLVE}" ]; then
    resolve_args=(--resolve "${DEPLOY_SMOKE_RESOLVE}")
  fi

  IFS=',' read -ra urls <<<"${urls_csv}"

  for url in "${urls[@]}"; do
    url="$(echo "${url}" | xargs)"

    if [ -z "${url}" ]; then
      continue
    fi

    ok=0

    for attempt in $(seq 1 "${retries}"); do
      if curl -fsS -o /dev/null --max-time 15 "${resolve_args[@]}" "${url}"; then
        ok=1
        break
      fi

      if [ "${attempt}" -lt "${retries}" ]; then
        sleep "${interval}"
      fi
    done

    if [ "${ok}" -ne 1 ]; then
      echo "Smoke check failed: ${url}" >&2
      return 1
    fi

    echo "Smoke check passed: ${url}"
  done
}

require_env "DEPLOY_BASE_PATH"
require_env "RELEASE_ID"
require_env "ARTIFACT_PATH"

# Resolved now: the script changes directory later and may have been started by a relative path.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"


DEPLOY_WEB_GROUP="${DEPLOY_WEB_GROUP:-www-data}"
DEPLOY_KEEP_RELEASES="${DEPLOY_KEEP_RELEASES:-5}"
# Seconds to let in-flight requests drain on retiring FPM workers before old
# release directories are removed. request_terminate_timeout is 60s, so raise
# this if long requests are still reading from a pruned release.
DEPLOY_PRUNE_GRACE_SECONDS="${DEPLOY_PRUNE_GRACE_SECONDS:-15}"
DEPLOY_SHARED_ENV_PATH="${DEPLOY_SHARED_ENV_PATH:-${DEPLOY_BASE_PATH}/shared/.env}"
DEPLOY_EXPECTED_APP_ENV="${DEPLOY_EXPECTED_APP_ENV:-production}"
# The first deploy onto a database that already holds the packages' tables (the live site's, copied or
# shared) records them with `filagora:adopt` instead of migrating them. It is its own step, never a
# side effect of migrate: a dry run first, which fails the deploy on anything it would not adopt.
DEPLOY_RUN_ADOPT="${DEPLOY_RUN_ADOPT:-0}"
DEPLOY_RUN_DOCTOR="${DEPLOY_RUN_DOCTOR:-1}"
DEPLOY_RUN_SEARCH_REINDEX="${DEPLOY_RUN_SEARCH_REINDEX:-0}"
DEPLOY_SEARCH_REINDEX_ARGS="${DEPLOY_SEARCH_REINDEX_ARGS:---sync-settings}"
DEPLOY_PHP_FPM_SERVICE="${DEPLOY_PHP_FPM_SERVICE:-php8.4-fpm}"
DEPLOY_WEB_SERVICE="${DEPLOY_WEB_SERVICE:-nginx}"
# Systemd units for the scheduler and the queue workers; empty skips the restart and the liveness check.
DEPLOY_SCHEDULER_SERVICE="${DEPLOY_SCHEDULER_SERVICE:-}"
DEPLOY_MIN_FREE_MB="${DEPLOY_MIN_FREE_MB:-1024}"
DEPLOY_QUEUE_WORKER_SERVICES="${DEPLOY_QUEUE_WORKER_SERVICES:-}"
# What `site:check-env` does with the shared .env before the switch: `off` skips it, `warn` prints the
# keys that need attention and carries on, `strict` fails the deploy on them.
DEPLOY_ENV_CHECK="${DEPLOY_ENV_CHECK:-warn}"
# Which machine this may run on. Both are empty by default (no restriction). DEPLOY_EXPECTED_HOSTNAME must
# equal `hostname`; DEPLOY_FORBIDDEN_ADDRESSES (comma-separated) refuses the run if the host has any of them.
# Use the second to keep a copy of production, whose hostname is the same, from deploying to the real one.
DEPLOY_EXPECTED_HOSTNAME="${DEPLOY_EXPECTED_HOSTNAME:-}"
DEPLOY_FORBIDDEN_ADDRESSES="${DEPLOY_FORBIDDEN_ADDRESSES:-}"
# After the switch, each comma-separated URL must answer with a status below 400 (for example
# https://www.example.com/up). Empty skips the check. If one fails, the previous release is made current
# again. DEPLOY_SMOKE_RESOLVE ("host:443:127.0.0.1") makes curl reach the origin directly, past the CDN.
DEPLOY_SMOKE_URLS="${DEPLOY_SMOKE_URLS:-}"
DEPLOY_SMOKE_RESOLVE="${DEPLOY_SMOKE_RESOLVE:-}"
DEPLOY_SMOKE_RETRIES="${DEPLOY_SMOKE_RETRIES:-5}"
DEPLOY_SMOKE_INTERVAL_SECONDS="${DEPLOY_SMOKE_INTERVAL_SECONDS:-3}"

assert_deploy_target

RELEASES_DIR="${DEPLOY_BASE_PATH}/releases"
SHARED_DIR="${DEPLOY_BASE_PATH}/shared"
SHARED_STORAGE_DIR="${SHARED_DIR}/storage"
SHARED_PUBLIC_STORAGE_DIR="${SHARED_STORAGE_DIR}/app/public"
SHARED_BUILD_DIR="${SHARED_DIR}/build"
CURRENT_LINK="${DEPLOY_BASE_PATH}/current"

# Disambiguate the on-disk release directory from the artifact's own identifier.
# The artifact's RELEASE_ID is fixed at package time and gets reused byte-for-byte
# if this deploy step is retried against the same cached artifact (e.g. GitHub
# Actions "re-run failed jobs"), so a fresh deploy-time suffix guarantees this
# specific deploy attempt always lands in a brand-new directory.
RELEASE_ID="${RELEASE_ID}-$(date +%s%N)"
NEW_RELEASE_DIR="${RELEASES_DIR}/${RELEASE_ID}"
STAGING_LINK="${DEPLOY_BASE_PATH}/.current-next"
ARTIFACT_SHA256_PATH="${ARTIFACT_PATH}.sha256"

mkdir -p "${RELEASES_DIR}" "${SHARED_STORAGE_DIR}" "${SHARED_BUILD_DIR}"

check_disk_space "${DEPLOY_BASE_PATH}" "${DEPLOY_MIN_FREE_MB}"

mkdir -p \
  "${SHARED_STORAGE_DIR}/app" \
  "${SHARED_STORAGE_DIR}/app/public" \
  "${SHARED_STORAGE_DIR}/app/public/plugins" \
  "${SHARED_STORAGE_DIR}/framework" \
  "${SHARED_STORAGE_DIR}/framework/cache" \
  "${SHARED_STORAGE_DIR}/framework/cache/data" \
  "${SHARED_STORAGE_DIR}/framework/sessions" \
  "${SHARED_STORAGE_DIR}/framework/testing" \
  "${SHARED_STORAGE_DIR}/framework/views" \
  "${SHARED_STORAGE_DIR}/logs"

if [ ! -f "${DEPLOY_SHARED_ENV_PATH}" ]; then
  echo "Shared environment file not found at ${DEPLOY_SHARED_ENV_PATH}" >&2
  echo "Create it before the first release-based deploy." >&2
  exit 1
fi

if dotenv_truthy "${DEPLOY_SHARED_ENV_PATH}" "DO_NOT_REDEPLOY"; then
  echo "Redeploy blocked because DO_NOT_REDEPLOY is enabled in ${DEPLOY_SHARED_ENV_PATH}" >&2
  exit 1
fi

SHARED_APP_ENV="$(read_dotenv_value "${DEPLOY_SHARED_ENV_PATH}" "APP_ENV")"

if [ -z "${SHARED_APP_ENV}" ]; then
  echo "Shared environment file is missing APP_ENV: ${DEPLOY_SHARED_ENV_PATH}" >&2
  exit 1
fi

if [ -n "${DEPLOY_EXPECTED_APP_ENV}" ] && [ "${DEPLOY_EXPECTED_APP_ENV}" != "any" ] && [ "${SHARED_APP_ENV}" != "${DEPLOY_EXPECTED_APP_ENV}" ]; then
  echo "Shared environment APP_ENV=${SHARED_APP_ENV} does not match expected ${DEPLOY_EXPECTED_APP_ENV}: ${DEPLOY_SHARED_ENV_PATH}" >&2
  echo "Refusing to deploy with a misconfigured shared environment file." >&2
  exit 1
fi

if [ -e "${NEW_RELEASE_DIR}" ]; then
  echo "Release directory already exists: ${NEW_RELEASE_DIR}" >&2
  exit 1
fi

cleanup() {
  rm -f "${STAGING_LINK}"
  rm -f "${ARTIFACT_PATH}" "${ARTIFACT_SHA256_PATH}"
}

trap cleanup EXIT

if [ -f "${ARTIFACT_SHA256_PATH}" ] && command -v sha256sum >/dev/null 2>&1; then
  (
    cd "$(dirname "${ARTIFACT_PATH}")"
    sha256sum -c "$(basename "${ARTIFACT_SHA256_PATH}")"
  )
fi

mkdir -p "${NEW_RELEASE_DIR}"
tar -xzf "${ARTIFACT_PATH}" -C "${NEW_RELEASE_DIR}"

RELEASE_METADATA_PATH="${NEW_RELEASE_DIR}/bootstrap/release.json"

if [ ! -f "${RELEASE_METADATA_PATH}" ]; then
  echo "Warning: release artifact is missing bootstrap/release.json; commit SHA will be unknown." >&2
  php -r '
    $metadata = [
      "release_id" => $argv[2],
      "deployment_id" => $argv[2],
      "commit_sha" => "unknown",
      "built_at" => null,
      "deployed_at" => null,
    ];
    file_put_contents($argv[1], json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
  ' "${RELEASE_METADATA_PATH}" "${RELEASE_ID}"
fi

php -r '
  $path = $argv[1];
  $metadata = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
  $metadata["deployment_id"] = $argv[2];
  $metadata["deployed_at"] = $argv[3];
  file_put_contents($path, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
' "${RELEASE_METADATA_PATH}" "${RELEASE_ID}" "$(date -u +%Y-%m-%dT%H:%M:%SZ)"

publish_release_build_assets "${NEW_RELEASE_DIR}/public/build" "${SHARED_BUILD_DIR}"

ln -sfn "${DEPLOY_SHARED_ENV_PATH}" "${NEW_RELEASE_DIR}/.env"
rm -rf "${NEW_RELEASE_DIR}/storage"
ln -sfn "${SHARED_STORAGE_DIR}" "${NEW_RELEASE_DIR}/storage"

mkdir -p "${NEW_RELEASE_DIR}/bootstrap/cache"

find "${NEW_RELEASE_DIR}/bootstrap/cache" -type d -exec chmod 775 {} +
find "${NEW_RELEASE_DIR}/bootstrap/cache" -type f -exec chmod 664 {} +
normalize_runtime_permissions "${SHARED_STORAGE_DIR}" "${DEPLOY_WEB_GROUP}"
normalize_runtime_permissions "${NEW_RELEASE_DIR}/bootstrap/cache" "${DEPLOY_WEB_GROUP}"

assert_group_can_write "${NEW_RELEASE_DIR}/bootstrap/cache" "${DEPLOY_WEB_GROUP}"
assert_group_can_write "${SHARED_STORAGE_DIR}" "${DEPLOY_WEB_GROUP}"
assert_default_group_acl "${SHARED_STORAGE_DIR}/framework/cache/data" "${DEPLOY_WEB_GROUP}"

if [ -e "${NEW_RELEASE_DIR}/public/build" ] && [ ! -L "${NEW_RELEASE_DIR}/public/build" ]; then
  rm -rf "${NEW_RELEASE_DIR}/public/build"
fi

ln -sfn "${SHARED_BUILD_DIR}" "${NEW_RELEASE_DIR}/public/build"

if [ -e "${NEW_RELEASE_DIR}/public/storage" ] && [ ! -L "${NEW_RELEASE_DIR}/public/storage" ]; then
  rm -rf "${NEW_RELEASE_DIR}/public/storage"
fi

cd "${NEW_RELEASE_DIR}"

php artisan optimize:clear
php artisan storage:link || true

# public/js/filament and public/css/filament are gitignored and the Package job
# installs with --no-scripts, so this is the only place the panel's assets get
# written. Unconditional on purpose: Filament and Livewire are hard
# dependencies, and a missing command must fail the deploy, not skip it.
php artisan filament:assets
php artisan livewire:publish --assets

assert_file_exists "${NEW_RELEASE_DIR}/public/js/filament/filament/app.js" "filament:assets"
assert_file_exists "${NEW_RELEASE_DIR}/public/css/filament/filament/app.css" "filament:assets"

if [ "${DEPLOY_RUN_ADOPT}" = "1" ]; then
  # Fails the deploy (set -e) if the dry run finds anything it would not adopt.
  php artisan filagora:adopt --dry-run
  php artisan filagora:adopt
fi

php artisan migrate --force

if [ "${DEPLOY_RUN_DOCTOR}" = "1" ]; then
  php artisan filagora:doctor
fi

check_environment_file

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

if ! php artisan forums:warm-cache --flush; then
  echo "Warning: forums cache warm failed; continuing deploy." >&2
fi

if ! php artisan plugins:warm-cache --flush; then
  echo "Warning: plugins cache warm failed; continuing deploy." >&2
fi

php artisan about >/dev/null

# Remembered for the smoke check's rollback. Empty on the first deploy, when there is nothing to go back to.
PREVIOUS_RELEASE_DIR="$(readlink -f "${CURRENT_LINK}" 2>/dev/null || true)"

ln -sfn "${NEW_RELEASE_DIR}" "${STAGING_LINK}"
mv -Tf "${STAGING_LINK}" "${CURRENT_LINK}"

cd "${CURRENT_LINK}"
php artisan queue:restart || true

if [ "${DEPLOY_RUN_SEARCH_REINDEX}" = "1" ]; then
  php artisan filagora:search:reindex ${DEPLOY_SEARCH_REINDEX_ARGS}
fi

chmod 755 "${CURRENT_LINK}/artisan"
normalize_runtime_permissions "${CURRENT_LINK}/bootstrap/cache" "${DEPLOY_WEB_GROUP}"

if [ -d "${CURRENT_LINK}/public/js" ]; then
  normalize_runtime_permissions "${CURRENT_LINK}/public/js" "${DEPLOY_WEB_GROUP}"
fi

if [ -d "${CURRENT_LINK}/public/css" ]; then
  normalize_runtime_permissions "${CURRENT_LINK}/public/css" "${DEPLOY_WEB_GROUP}"
fi

assert_group_can_write "${CURRENT_LINK}/bootstrap/cache" "${DEPLOY_WEB_GROUP}"

cd "$DEPLOY_BASE_PATH/current"

php artisan optimize

# bootstrap/cache/config.php holds every resolved secret in .env - Meilisearch
# and mail credentials, APP_KEY, the DB password - so it must not keep the
# world-readable bit that normalize_runtime_permissions' 664 pass and the
# default ACL on the directory would otherwise leave on it. This has to run
# after `optimize`, which rewrites the file that `config:cache` already wrote
# earlier in the deploy; a chmod before this point is silently undone.
#
# find, not a glob: under `set -euo pipefail` an unmatched bootstrap/cache/*.php
# would pass the literal pattern to chmod and abort the deploy. The directory
# itself stays 2775 for assert_group_can_write. On a file carrying the default
# ACL, chmod's group bits set the ACL mask, which clamps g:www-data to an
# effective r-- - read is all PHP-FPM needs here.
find "${CURRENT_LINK}/bootstrap/cache" -type f -name '*.php' -exec chmod 640 {} +

# reload, not restart: SIGUSR2 keeps the FPM master's listening socket bound
# while it re-execs and retires workers. A restart unlinks
# /run/php/php8.4-fpm.sock between stop and start, and every request arriving
# in that gap fails with "connect() to unix:... failed (2: No such file or
# directory)". The re-exec still tears down and rebuilds the OPcache SHM, so
# the new release's code is picked up either way.
run_privileged systemctl reload "${DEPLOY_PHP_FPM_SERVICE}"
run_privileged systemctl reload "${DEPLOY_WEB_SERVICE}"

# `schedule:work` resolves its working directory (and therefore
# routes/console.php) once at start, so a scheduler left running keeps
# executing the *previous* release's scheduled tasks until it is restarted.
# queue:restart above does not touch it. Skip silently when there is no such
# unit — a cron-driven `schedule:run` re-bootstraps every minute and needs
# nothing here.
if [ -n "${DEPLOY_SCHEDULER_SERVICE}" ] && systemctl cat "${DEPLOY_SCHEDULER_SERVICE}" >/dev/null 2>&1; then
  run_privileged systemctl restart "${DEPLOY_SCHEDULER_SERVICE}"
fi

# Before anything is pruned, so a failed deploy still has its previous release to go back to. Only the code
# is rolled back: migrations that already ran stay, so release migrations must stay compatible with the
# previous release's code (add first, remove a release later).
if [ -n "${DEPLOY_SMOKE_URLS}" ] && ! smoke_check_urls "${DEPLOY_SMOKE_URLS}"; then
  if [ -n "${PREVIOUS_RELEASE_DIR}" ] && [ -d "${PREVIOUS_RELEASE_DIR}" ]; then
    echo "Rolling back to $(basename "${PREVIOUS_RELEASE_DIR}"); migrations from this release were not reverted." >&2
    DEPLOY_BASE_PATH="${DEPLOY_BASE_PATH}" \
      RELEASE_ID="$(basename "${PREVIOUS_RELEASE_DIR}")" \
      DEPLOY_PHP_FPM_SERVICE="${DEPLOY_PHP_FPM_SERVICE}" \
      DEPLOY_WEB_SERVICE="${DEPLOY_WEB_SERVICE}" \
      DEPLOY_QUEUE_WORKER_SERVICES="${DEPLOY_QUEUE_WORKER_SERVICES}" \
      bash "${SCRIPT_DIR}/rollback_release.sh" || echo "Automatic rollback failed; roll back by hand." >&2
  else
    echo "No previous release to roll back to." >&2
  fi

  exit 1
fi

# Retire old releases only after the reload has moved workers onto the new
# one. Swapping the "current" symlink does not move requests already in
# flight: their workers hold OPcache entries keyed to the old release's real
# path, and Composer's autoloader includes class files lazily, so a class
# first needed late in a request is read from disk mid-request. Deleting that
# directory first turned those reads into "Failed to open stream: No such
# file or directory" fatals — and because the missing files included the
# error renderer and PSR log level, the handler died while reporting, so
# nothing reached Laravel's log or Honeybadger. Every HTTP 500 on 20 Aug 2026
# landed in the five one-minute windows around a deploy.
#
# A graceful reload retires each worker only when it finishes its current
# request, so wait briefly for the stragglers before unlinking anything.
sleep "${DEPLOY_PRUNE_GRACE_SECONDS}"

# Sort by mtime, not by name. A plain sort is only chronological when every
# release shares a naming scheme, and these do not: CI writes
# "release-<run>-<attempt>-...", manual deploys write "manual-<timestamp>-...".
# Since "m" sorts before "r", every manual release sorted ahead of every CI
# release regardless of age, so pruning from the front removed the most recent
# manual release while keeping far older CI ones — which is how a release
# still serving live requests came to be deleted.
mapfile -t releases < <(find "${RELEASES_DIR}" -mindepth 1 -maxdepth 1 -type d -printf '%T@\t%p\n' | sort -n | cut -f2-)

if [ "${#releases[@]}" -gt "${DEPLOY_KEEP_RELEASES}" ]; then
  releases_to_remove=$(( ${#releases[@]} - DEPLOY_KEEP_RELEASES ))
  for release_path in "${releases[@]:0:${releases_to_remove}}"; do
    if [ "${release_path}" != "${NEW_RELEASE_DIR}" ] && [ "${release_path}" != "$(readlink -f "${CURRENT_LINK}")" ]; then
      # A plain unprivileged rm here fails with "Permission denied" on any
      # release whose files ended up owned by someone other than the deploy
      # user (e.g. an artifact extracted under a different identity during a
      # one-off manual/root deploy). set -e then aborts the whole script at
      # the prune step, after the new release is already live, so pruning
      # silently never recovers on its own. Route through the same privileged
      # path the rest of the script already uses for ownership repair.
      run_privileged rm -rf "${release_path}"
    fi
  done
fi

if [ -n "${DEPLOY_QUEUE_WORKER_SERVICES}" ]; then
  assert_queue_workers_active "${DEPLOY_QUEUE_WORKER_SERVICES}"
fi

echo "Deployment complete: ${RELEASE_ID}"
