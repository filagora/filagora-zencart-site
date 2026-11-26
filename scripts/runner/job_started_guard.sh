#!/usr/bin/env bash
# Job-started hook for the deploy runner: only the Deploy workflow, started by hand from master, may use it.
#
# The runner host keeps its own copy, owned by root, so that nothing in a job can change it (see
# docs/deployment.md, "The runner guard"). A change here takes effect only when that copy is replaced.
#
# To the runner a failing hook is one failed step: later steps and actions that ask to run `always()` still
# run. Refusing therefore means killing the runner's worker process, which is this script's parent. Any
# other way out of this script, an error included, refuses too.

refuse() {
    echo "::error::This runner only runs the Deploy workflow, started by hand from master."
    kill -KILL "${PPID}"
}
trap refuse EXIT

repository='filagora/filagora-zencart-site'

[ "${GITHUB_REPOSITORY:-}" = "${repository}" ] || exit 1
[ "${GITHUB_EVENT_NAME:-}" = 'workflow_dispatch' ] || exit 1
[ "${GITHUB_REF:-}" = 'refs/heads/master' ] || exit 1
[ "${GITHUB_WORKFLOW_REF:-}" = "${repository}/.github/workflows/deploy.yml@refs/heads/master" ] || exit 1

trap - EXIT
