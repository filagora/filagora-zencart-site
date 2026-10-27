#!/usr/bin/env bash

set -euo pipefail

STATE_FILE="${NFTABLES_MOCK_STATE_FILE:-/tmp/lara-forum-nftables-mock-state.json}"

seed_state() {
    cat >"$STATE_FILE" <<'JSON'
{
  "nftables": [
    { "metainfo": { "version": "1.0.9", "release_name": "mock" } },
    { "table": { "family": "inet", "name": "filter", "handle": 1 } },
    { "chain": { "family": "inet", "table": "filter", "name": "input", "type": "filter", "hook": "input", "prio": 0, "policy": "accept" } },
    { "rule": { "family": "inet", "table": "filter", "chain": "input", "expr": [] } },
    { "set": { "family": "inet", "table": "filter", "name": "blocked_ipv4", "type": "ipv4_addr", "flags": ["timeout"], "elem": ["203.0.113.10", "203.0.113.11"] } },
    { "set": { "family": "inet", "table": "filter", "name": "blocked_ipv6", "type": "ipv6_addr", "elem": ["2001:db8::10"] } }
  ]
}
JSON
}

ensure_state() {
    if [[ ! -f "$STATE_FILE" ]]; then
        seed_state
    fi
}

remove_element() {
    local family="$1"
    local table="$2"
    local set_name="$3"
    local element="$4"

    php -r '
    $path = $argv[1];
    $family = $argv[2];
    $table = $argv[3];
    $setName = $argv[4];
    $element = $argv[5];
    $state = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    foreach ($state["nftables"] as &$entry) {
        if (! isset($entry["set"]) || ! is_array($entry["set"])) {
            continue;
        }

        $set = &$entry["set"];

        if (($set["family"] ?? null) !== $family || ($set["table"] ?? null) !== $table || ($set["name"] ?? null) !== $setName) {
            continue;
        }

        $elements = is_array($set["elem"] ?? null) ? $set["elem"] : [];
        $set["elem"] = array_values(array_filter($elements, static fn ($value) => (string) $value !== $element));
    }

    file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    ' "$STATE_FILE" "$family" "$table" "$set_name" "$element"
}

ensure_state

command="${1:-}"

case "$command" in
    ruleset)
        cat "$STATE_FILE"
        ;;
    remove-element)
        remove_element "${2:-}" "${3:-}" "${4:-}" "${5:-}"
        echo "removed"
        ;;
    reset)
        seed_state
        cat "$STATE_FILE"
        ;;
    *)
        echo "Unknown command: $command" >&2
        exit 1
        ;;
esac
