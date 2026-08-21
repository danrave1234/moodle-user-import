<?php

declare(strict_types=1);

namespace App\Http;

use App\Import\Model\ImportPreview;
use App\Import\Model\ImportResult;
use App\Import\Model\ProcessedRow;
use App\Import\Model\ValidationError;

final class ImportResponseMapper
{
    /** @return array{summary: array{total: int, valid: int, invalid: int}, rows: list<array<string, mixed>>} */
    public function preview(ImportPreview $preview): array
    {
        return [
            'summary' => [
                'total' => $preview->total,
                'valid' => $preview->valid,
                'invalid' => $preview->invalid,
            ],
            'rows' => array_map($this->row(...), $preview->rows),
        ];
    }

    /** @return array{summary: array{total: int, valid: int, invalid: int, imported: int, skipped: int}, imported: list<array<string, mixed>>, rejected: list<array<string, mixed>>} */
    public function result(ImportResult $result): array
    {
        return [
            'summary' => [
                'total' => $result->total,
                'valid' => $result->valid,
                'invalid' => $result->invalid,
                'imported' => $result->imported,
                'skipped' => $result->skipped,
            ],
            'imported' => array_map($this->importedRow(...), $result->importedRows),
            'rejected' => array_map($this->row(...), $result->rejectedRows),
        ];
    }

    /** @return array{rowNumber: int, name: string, surname: string, email: string} */
    private function importedRow(ProcessedRow $row): array
    {
        return [
            'rowNumber' => $row->rowNumber,
            'name' => $row->candidate->name,
            'surname' => $row->candidate->surname,
            'email' => $row->candidate->email,
        ];
    }

    /** @return array<string, mixed> */
    private function row(ProcessedRow $row): array
    {
        return [
            'rowNumber' => $row->rowNumber,
            'name' => $row->candidate->name,
            'surname' => $row->candidate->surname,
            'email' => $row->candidate->email,
            'status' => $row->isValid() ? 'valid' : 'invalid',
            'errors' => array_map(
                static fn (ValidationError $error): array => [
                    'field' => $error->field,
                    'code' => $error->code,
                    'message' => $error->message,
                ],
                $row->errors,
            ),
        ];
    }
}
