<?php

declare(strict_types=1);

namespace App\Import\Csv;

use App\Import\Model\RawUserRow;

interface CsvReader
{
    /** @return iterable<RawUserRow> */
    public function read(string $path): iterable;
}
