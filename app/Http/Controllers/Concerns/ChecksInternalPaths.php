<?php

namespace App\Http\Controllers\Concerns;

trait ChecksInternalPaths
{
    /**
     * The path if it stays on this site, otherwise an empty string. Anything but a string, such as
     * `return_to[]=x`, counts as empty.
     */
    private function internalPath(mixed $path): string
    {
        $trimmed = is_string($path) ? trim($path) : '';

        if ($trimmed === ''
            || preg_match('/[\x00-\x1F\x7F]/', $trimmed) === 1
            || preg_match('#^/[^/\\\\]#', $trimmed) !== 1) {
            return '';
        }

        return $trimmed;
    }
}
