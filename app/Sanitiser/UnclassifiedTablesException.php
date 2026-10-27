<?php

namespace App\Sanitiser;

use RuntimeException;

class UnclassifiedTablesException extends RuntimeException
{
    /**
     * @param  list<string>  $tables
     */
    public function __construct(public readonly array $tables)
    {
        parent::__construct('The sanitiser policy does not name these tables: '.implode(', ', $tables));
    }
}
