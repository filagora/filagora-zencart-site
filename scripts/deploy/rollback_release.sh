#!/usr/bin/env bash

set -euo pipefail

require_env() {
  local name="$1"
  if [ -z "${!name:-}" ]; then
    echo "Missing required environment variable: ${name}" >&2
    exit 1
  fi
}

require_env "DEPLOY_BASE_PATH"
require_env "RELEASE_ID"

run_privileged() {
  if command -v sudo >/dev/null 2>&1; then
    sudo -n "$@"
    return
  fi

  "$@"
}

DEPLOY_PHP_FPM_SERVICE="${DEPLOY_PHP_FPM_SERVICE:-php8.4-fpm}"
DEPLOY_WEB_SERVICE="${DEPLOY_WEB_SERVICE:-nginx}"
DEPLOY_QUEUE_WORKER_SERVICES="${DEPLOY_QUEUE_WORKER_SERVICES:-}"

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
    echo "Rollback aborted: queue worker service(s) not active after rollback:" >&2
    for failure in "${failures[@]}"; do
      echo "  - ${failure}" >&2
    done
    exit 1
  fi
}

RELEASES_DIR="${DEPLOY_BASE_PATH}/releases"
CURRENT_LINK="${DEPLOY_BASE_PATH}/current"
STAGING_LINK="${DEPLOY_BASE_PATH}/.current-rollback"
TARGET_RELEASE_DIR="${RELEASES_DIR}/${RELEASE_ID}"

if [ ! -d "${TARGET_RELEASE_DIR}" ]; then
  echo "Release not found: ${TARGET_RELEASE_DIR}" >&2
  echo "Available releases:" >&2
  find "${RELEASES_DIR}" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort >&2 || true
  exit 1
fi

if [ ! -f "${TARGET_RELEASE_DIR}/artisan" ]; then
  echo "Target release is missing artisan: ${TARGET_RELEASE_DIR}" >&2
  exit 1
fi

cleanup() {
  rm -f "${STAGING_LINK}"
}

trap cleanup EXIT

ln -sfn "${TARGET_RELEASE_DIR}" "${STAGING_LINK}"
mv -Tf "${STAGING_LINK}" "${CURRENT_LINK}"

cd "${CURRENT_LINK}"
php artisan about >/dev/null
php artisan queue:restart || true

php artisan optimize:clear

# reload rather than restart, for the same reason as release_deploy.sh: a
# restart unlinks the FPM socket and drops every in-flight request. A rollback
# is usually run while the site is already unhappy, so dropping requests on the
# way back is the last thing wanted.
run_privileged systemctl reload "${DEPLOY_PHP_FPM_SERVICE}"
run_privileged systemctl reload "${DEPLOY_WEB_SERVICE}"

if [ -n "${DEPLOY_QUEUE_WORKER_SERVICES}" ]; then
  assert_queue_workers_active "${DEPLOY_QUEUE_WORKER_SERVICES}"
fi

echo "Rolled back current release to ${RELEASE_ID}"
