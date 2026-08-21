<?php

declare(strict_types=1);

namespace App\Import;

use App\Import\Csv\CsvReader;
use App\Import\Model\ImportPreview;
use App\Import\Model\ImportResult;
use App\Import\Model\ProcessedRow;
use App\Import\Model\ValidationError;
use App\Persistence\UserRepository;
use Throwable;

final readonly class UserImportService
{
    public function __construct(
        private CsvReader $csvReader,
        private UserNormalizer $normalizer,
        private UserValidator $validator,
        private UserRepository $repository,
    ) {
    }

    public function preview(string $path): ImportPreview
    {
        $rows = [];
        $seenEmails = [];

        foreach ($this->csvReader->read($path) as $rawRow) {
            $candidate = $this->normalizer->normalize($rawRow);
            $errors = $this->validator->validate($candidate);

            if ($candidate->email !== '') {
                if (isset($seenEmails[$candidate->email])) {
                    $errors[] = new ValidationError(
                        'email',
                        'duplicate_in_file',
                        'This email is repeated in the CSV file.',
                    );
                } else {
                    $seenEmails[$candidate->email] = true;
                }
            }

            $rows[] = new ProcessedRow($rawRow->rowNumber, $candidate, $errors);
        }

        return new ImportPreview($this->markDatabaseDuplicates($rows));
    }

    public function import(string $path): ImportResult
    {
        $preview = $this->preview($path);
        $imported = 0;

        $this->repository->beginTransaction();

        try {
            foreach ($preview->rows as $row) {
                if ($row->isValid() && $this->repository->insert($row->candidate)) {
                    ++$imported;
                }
            }

            $this->repository->commit();
        } catch (Throwable $exception) {
            $this->repository->rollBack();
            throw $exception;
        }

        return new ImportResult($preview->rows, $imported);
    }

    /**
     * @param list<ProcessedRow> $rows
     * @return list<ProcessedRow>
     */
    private function markDatabaseDuplicates(array $rows): array
    {
        $emails = array_values(array_map(
            static fn (ProcessedRow $row): string => $row->candidate->email,
            array_filter($rows, static fn (ProcessedRow $row): bool => $row->isValid()),
        ));
        $existing = array_fill_keys($this->repository->findExistingEmails($emails), true);

        return array_map(
            static function (ProcessedRow $row) use ($existing): ProcessedRow {
                if (!$row->isValid() || !isset($existing[$row->candidate->email])) {
                    return $row;
                }

                return $row->withError(new ValidationError(
                    'email',
                    'duplicate_in_database',
                    'This email already exists.',
                ));
            },
            $rows,
        );
    }
}
