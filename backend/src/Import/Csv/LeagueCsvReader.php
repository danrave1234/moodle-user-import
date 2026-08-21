<?php

declare(strict_types=1);

namespace App\Import\Csv;

use App\Import\Model\RawUserRow;
use League\Csv\Reader;
use Throwable;

final class LeagueCsvReader implements CsvReader
{
    private const REQUIRED_HEADERS = ['name', 'surname', 'email'];

    public function read(string $path): iterable
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new CsvReadException('The CSV file could not be read.');
        }

        try {
            $csv = Reader::from($path, 'r');
            $csv->setHeaderOffset(0);
            $headerMap = $this->headerMap($csv->getHeader());

            foreach ($csv->getRecords() as $offset => $record) {
                yield new RawUserRow(
                    rowNumber: $offset + 1,
                    name: $this->value($record, $headerMap['name']),
                    surname: $this->value($record, $headerMap['surname']),
                    email: $this->value($record, $headerMap['email']),
                );
            }
        } catch (CsvReadException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CsvReadException('The file is not a readable CSV document.', previous: $exception);
        }
    }

    /**
     * @param array<string> $headers
     * @return array{name: string, surname: string, email: string}
     */
    private function headerMap(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $header) {
            $key = mb_strtolower(trim($header), 'UTF-8');
            if ($key !== '') {
                $normalized[$key] = $header;
            }
        }

        $missing = array_diff(self::REQUIRED_HEADERS, array_keys($normalized));
        if ($missing !== []) {
            throw new CsvReadException('Missing required CSV headers: ' . implode(', ', $missing) . '.');
        }

        return [
            'name' => $normalized['name'],
            'surname' => $normalized['surname'],
            'email' => $normalized['email'],
        ];
    }

    /** @param array<string, string|null> $record */
    private function value(array $record, string $header): string
    {
        return (string) ($record[$header] ?? '');
    }
}
