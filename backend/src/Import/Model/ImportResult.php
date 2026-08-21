<?php

declare(strict_types=1);

namespace App\Import\Model;

final readonly class ImportResult
{
    public int $total;
    public int $valid;
    public int $invalid;
    public int $skipped;

    /** @param list<ProcessedRow> $rows */
    public function __construct(
        public array $rows,
        public int $imported,
    ) {
        $this->total = count($rows);
        $this->valid = count(array_filter($rows, static fn (ProcessedRow $row): bool => $row->isValid()));
        $this->invalid = $this->total - $this->valid;
        $this->skipped = $this->valid - $this->imported;
    }
}
