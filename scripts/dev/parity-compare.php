<?php

/**
 * Compares two replay results (old app, new app) and prints the differences.
 *
 * Usage: php scripts/dev/parity-compare.php <old.tsv> <new.tsv> [intended-differences.txt]
 *
 * Compares status, redirect target, content type and the cacheability class of Cache-Control.
 * Timing is reported separately as old/new medians and p95. A line in the intended file is a URL
 * prefix whose differences have been reviewed and accepted.
 */
[, $oldFile, $newFile, $intendedFile] = $argv + [null, null, null, null];

$read = function (string $file): array {
    $rows = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        $c = explode("\t", $line);
        $rows[$c[0]] = $c + array_fill(0, 6, '');
    }

    return $rows;
};

$old = $read((string) $oldFile);
$new = $read((string) $newFile);
$intended = $intendedFile && is_file($intendedFile) ? array_filter(array_map('trim', file($intendedFile))) : [];

$cache = fn (string $v): string => match (true) {
    $v === '' => 'none',
    str_contains($v, 'no-store') || str_contains($v, 'no-cache') || str_contains($v, 'private') => 'private',
    str_contains($v, 'public') => 'public',
    default => 'other',
};

$diffs = [];
foreach ($old as $url => $o) {
    $n = $new[$url] ?? null;
    if ($n === null) {
        continue;
    }
    $fields = [];
    foreach ([1 => 'status', 2 => 'location', 4 => 'type'] as $i => $label) {
        if ($o[$i] !== $n[$i]) {
            $fields[] = "$label {$o[$i]} -> {$n[$i]}";
        }
    }
    if ($cache($o[3]) !== $cache($n[3])) {
        $fields[] = 'cache '.$cache($o[3]).' -> '.$cache($n[3]);
    }
    if ($fields === []) {
        continue;
    }
    foreach ($intended as $prefix) {
        if (str_starts_with($url, $prefix)) {
            continue 2;
        }
    }
    $diffs[$url] = implode('; ', $fields);
}

$stats = function (array $rows): string {
    $t = array_map(fn ($r) => (float) $r[5], $rows);
    sort($t);
    $count = count($t);

    return $count === 0 ? 'n/a' : sprintf('p50 %.3fs p95 %.3fs', $t[(int) ($count * 0.5)], $t[(int) ($count * 0.95)]);
};

ksort($diffs);
foreach ($diffs as $url => $what) {
    echo "$url\t$what\n";
}
fwrite(STDERR, count($old).' compared, '.count($diffs)." differ\nold ".$stats($old)."\nnew ".$stats($new)."\n");
