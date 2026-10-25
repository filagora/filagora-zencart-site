<?php

namespace App\Http\Controllers;

/**
 * Stands in for a page whose slice is not ported yet; see routes/pending.php.
 */
class NotYetPorted
{
    public function __invoke(): never
    {
        abort(404);
    }
}
