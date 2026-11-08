<?php

/**
 * Replays a corpus against one base URL and records what matters for parity.
 *
 * Usage: php scripts/dev/parity-replay.php <corpus.tsv> <baseUrl> <result.tsv> [hostHeader]
 *
 * Redirects are not followed: the status and the Location path are the answer. Output columns:
 * url, status, redirect target (path only), cache-control, content-type (media type only),
 * seconds. Run it once per app, then diff with parity-compare.php.
 */
[$script, $corpus, $base, $out] = $argv + [null, null, null, null];

if (! is_file((string) $corpus) || $base === null || $out === null) {
    fwrite(STDERR, "Usage: php parity-replay.php <corpus.tsv> <baseUrl> <result.tsv>\n");
    exit(1);
}

$lines = array_filter(array_map('trim', file($corpus)));
$result = fopen($out, 'w');
$n = 0;

foreach ($lines as $line) {
    [$url] = explode("\t", $line);
    $ch = curl_init(rtrim($base, '/').$url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'parity-replay',
        CURLOPT_HTTPHEADER => ['X-Forwarded-Proto: https'],
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $headers = $raw === false ? '' : substr($raw, 0, (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    curl_close($ch);

    $header = function (string $name) use ($headers): string {
        return preg_match('/^'.preg_quote($name, '/').':\s*(.*)$/mi', $headers, $m) ? trim($m[1]) : '';
    };

    $location = $header('Location');
    $locationPath = $location === '' ? '' : (parse_url($location, PHP_URL_PATH) ?: '/').(($q = parse_url($location, PHP_URL_QUERY)) ? '?'.$q : '');
    $type = trim(explode(';', $header('Content-Type'))[0]);

    fputcsv($result, [$url, $status, $locationPath, $header('Cache-Control'), $type, round($time, 3)], "\t");
    if (++$n % 200 === 0) {
        fwrite(STDERR, "$n/".count($lines)."\n");
    }
}

fclose($result);
