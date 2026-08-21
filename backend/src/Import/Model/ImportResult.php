<?php

declare(strict_types=1);

namespace App\Import\Model;

final readonly class ImportResult
{
    public int $total;
    public int $valid;
    public int $invalid;
    public int $skipped;
    public int $imported;

    /**
     * @param list<ProcessedRow> $rows
     * @param list<ProcessedRow> $importedRows
     * @param list<ProcessedRow> $rejectedRows
     */
    public function __construct(
        public array $rows,
        public array $importedRows,
        public array $rejectedRows,
    ) {
        $this->total = count($rows);
        $this->valid = count(array_filter($rows, static fn (ProcessedRow $row): bool => $row->isValid()));
        $this->invalid = $this->total - $this->valid;
        $this->imported = count($importedRows);
        $this->skipped = count($rejectedRows);
    }
}
