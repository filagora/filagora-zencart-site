<?php

/**
 * Turns a "METHOD /path?query STATUS" access-log extract into a URL replay corpus.
 *
 * Usage: php scripts/dev/parity-corpus.php <extract.txt> <corpus.tsv> [perGroupLimit=250]
 *
 * Keeps GET/HEAD only; drops blocked or noisy requests; keeps only an allow-list of query
 * parameters (everything else can hold session ids, tokens, search terms or other personal data);
 * dedupes; and samples at most perGroupLimit URLs per first path segment, with a fixed seed so the
 * same extract gives the same corpus. Legacy .php endpoints are kept in full because they are
 * what the redirect layer exists for. Output: path-and-query <TAB> old status.
 */
$in = $argv[1] ?? null;
$out = $argv[2] ?? null;
$limit = (int) ($argv[3] ?? 250);

if ($in === null || $out === null || ! is_file($in)) {
    fwrite(STDERR, "Usage: php parity-corpus.php <extract.txt> <corpus.tsv> [perGroupLimit]\n");
    exit(1);
}

const ALLOWED = [
    'page', 'p', 'mode', 'do', 'id', 'goto', 'attachmentid', 'disposition', 'sort', 'order', 't', 'f', 'postcount',
    'type', 'highlight', 'encapsulated', 'threadid', 'postid', 'forumid', 'u', 'userid', 'pp', 'daysprune', 'cat',
    'catid', 'fileid', 'd', 'tab', 'view', 'filter',
];
const SKIP_STATUS = [400, 403, 408, 429, 444, 499, 500, 502, 503, 504];
const SKIP_FIRST = ['up', 'sw.js', 'x', 'webhooks', 'livewire', 'filament', 'dashboard', 'login', 'logout', 'register'];
const SKIP_WORDS = ['unsubscribe', 'verify', 'reset', 'token', 'signature', 'signed', 'password', 'preview', 'download'];

$groups = [];

foreach (new SplitFileIterator($in) as $line) {
    $parts = explode(' ', trim($line));

    if (count($parts) !== 3 || ! in_array($parts[0], ['GET', 'HEAD'], true) || ! str_starts_with($parts[1], '/')) {
        continue;
    }

    [, $target, $status] = $parts;

    if (in_array((int) $status, SKIP_STATUS, true)) {
        continue;
    }

    $path = parse_url($target, PHP_URL_PATH) ?: '/';
    parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
    $query = array_filter($query, fn ($v, $k) => in_array($k, ALLOWED, true) && is_scalar($v) && strlen((string) $v) <= 40, ARRAY_FILTER_USE_BOTH);
    ksort($query);

    $segments = explode('/', ltrim($path, '/'));
    $first = $segments[0];

    if (in_array($first, SKIP_FIRST, true) || preg_match('/('.implode('|', SKIP_WORDS).')/i', $path.http_build_query($query))) {
        continue;
    }

    if (strlen($path) > 200 || preg_match('/[^\x20-\x7e]/', $path)) {
        continue;
    }

    $url = $path.($query === [] ? '' : '?'.http_build_query($query));
    $group = str_ends_with($first, '.php') ? $first : ($first === '' ? '/' : $first);
    $groups[$group][$url] = $status;
}

mt_srand(20261108);
$rows = [];

foreach ($groups as $group => $urls) {
    $keys = array_keys($urls);

    if (! str_ends_with((string) $group, '.php') && count($keys) > $limit) {
        shuffle($keys);
        $keys = array_slice($keys, 0, $limit);
    }

    foreach ($keys as $url) {
        $rows[] = $url."\t".$urls[$url];
    }
}

sort($rows);
file_put_contents($out, implode("\n", $rows)."\n");
fwrite(STDERR, count($rows).' URLs in '.count($groups)." groups\n");

class SplitFileIterator extends IteratorIterator
{
    public function __construct(string $file)
    {
        parent::__construct(new SplFileObject($file));
    }
}
