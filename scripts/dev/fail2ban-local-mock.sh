#!/usr/bin/env bash

set -euo pipefail

STATE_FILE="${FAIL2BAN_MOCK_STATE_FILE:-/tmp/lara-forum-fail2ban-mock-state.json}"

read_state() {
  if [[ ! -s "${STATE_FILE}" ]]; then
    cat <<'JSON' > "${STATE_FILE}"
{"jails":[{"name":"sshd","currently_failed":2,"total_failed":492,"total_banned":34,"banned_ips":["203.0.113.10","203.0.113.11","203.0.113.12","203.0.113.13","203.0.113.14","203.0.113.15","203.0.113.16","203.0.113.17","203.0.113.18","203.0.113.19","203.0.113.20","2001:db8::10"],"log_files":[],"journal_matches":"_SYSTEMD_UNIT=ssh.service + _COMM=sshd"},{"name":"nginx-http-auth","currently_failed":0,"total_failed":41,"total_banned":9,"banned_ips":["198.51.100.20","198.51.100.21"],"log_files":["/var/log/nginx/error.log"],"journal_matches":""}]}
JSON
  fi

  cat "${STATE_FILE}"
}

validate_jail() {
  local jail="$1"

  [[ "${jail}" =~ ^[A-Za-z0-9._-]+$ ]]
}

validate_ip() {
  local ip="$1"

  [[ "${ip}" =~ ^[0-9A-Fa-f:.]+$ ]]
}

status_output() {
  read_state | php -r '
    $state = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $jails = array_map(static fn (array $jail): string => $jail["name"], $state["jails"] ?? []);

    echo "Status\n";
    echo "|- Number of jail:      " . count($jails) . "\n";
    echo "`- Jail list:   " . implode(", ", $jails) . "\n";
  '
}

jail_status_output() {
  local jail="$1"

  read_state | php -r '
    $target = $argv[1];
    $state = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);

    foreach ($state["jails"] ?? [] as $jail) {
        if (($jail["name"] ?? null) !== $target) {
            continue;
        }

        $bannedIps = $jail["banned_ips"] ?? [];
        $logFiles = $jail["log_files"] ?? [];
        $journalMatches = trim((string) ($jail["journal_matches"] ?? ""));

        echo "Status for the jail: {$target}\n";
        echo "|- Filter\n";
        echo "|  |- Currently failed:  " . (int) ($jail["currently_failed"] ?? 0) . "\n";
        echo "|  |- Total failed:      " . (int) ($jail["total_failed"] ?? 0) . "\n";

        if ($journalMatches !== "") {
            echo "|  `- Journal matches:   {$journalMatches}\n";
        } elseif ($logFiles !== []) {
            echo "|  `- File list:         " . array_shift($logFiles) . "\n";

            foreach ($logFiles as $logFile) {
                echo "|                         {$logFile}\n";
            }
        } else {
            echo "|  `- File list:\n";
        }

        echo "`- Actions\n";
        echo "   |- Currently banned:  " . count($bannedIps) . "\n";
        echo "   |- Total banned:      " . (int) ($jail["total_banned"] ?? count($bannedIps)) . "\n";

        if ($bannedIps === []) {
            echo "   `- Banned IP list:\n";
        } else {
            echo "   `- Banned IP list:    " . array_shift($bannedIps) . "\n";

            foreach ($bannedIps as $ip) {
                echo "                         {$ip}\n";
            }
        }

        exit(0);
    }

    fwrite(STDERR, "Unknown jail: {$target}\n");
    exit(1);
  ' "${jail}"
}

unban_ip() {
  local jail="$1"
  local ip="$2"

  read_state | php -r '
    $targetJail = $argv[1];
    $targetIp = $argv[2];
    $stateFile = $argv[3];
    $state = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $updated = false;

    foreach (array_keys($state["jails"] ?? []) as $index) {
        if (($state["jails"][$index]["name"] ?? null) !== $targetJail) {
            continue;
        }

        $state["jails"][$index]["banned_ips"] = array_values(array_filter(
            $state["jails"][$index]["banned_ips"] ?? [],
            static fn (string $bannedIp): bool => $bannedIp !== $targetIp,
        ));
        $updated = true;
        break;
    }

    if (! $updated) {
        fwrite(STDERR, "Unknown jail: {$targetJail}\n");
        exit(1);
    }

    file_put_contents(
        $stateFile,
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    );

    echo "1\n";
  ' "${jail}" "${ip}" "${STATE_FILE}"
}

action="${1:-}"

case "${action}" in
  status)
    status_output
    ;;
  jail-status)
    jail="${2:-}"
    validate_jail "${jail}" || exit 2
    jail_status_output "${jail}"
    ;;
  unban)
    jail="${2:-}"
    ip="${3:-}"
    validate_jail "${jail}" || exit 2
    validate_ip "${ip}" || exit 2
    unban_ip "${jail}" "${ip}"
    ;;
  reset)
    rm -f "${STATE_FILE}"
    read_state >/dev/null
    ;;
  *)
    echo "Usage: $0 {status|jail-status <jail>|unban <jail> <ip>|reset}" >&2
    exit 2
    ;;
esac
