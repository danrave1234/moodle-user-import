<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Csv;

use App\Import\Csv\CsvReadException;
use App\Import\Csv\LeagueCsvReader;
use App\Import\Model\RawUserRow;
use PHPUnit\Framework\TestCase;

final class LeagueCsvReaderTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            unlink($file);
        }
    }

    public function testItParsesQuotedValuesAndPreservesRowNumbers(): void
    {
        $rows = $this->read("name,surname,email\n\"John, Jr.\",Smith,john@example.com\n");

        self::assertCount(1, $rows);
        self::assertSame(2, $rows[0]->rowNumber);
        self::assertSame('John, Jr.', $rows[0]->name);
    }

    public function testItAllowsAlternateColumnOrderAndExtraColumns(): void
    {
        $rows = $this->read("email,role,surname,name\njane@example.com,student,Doe,Jane\n");

        self::assertSame('Jane', $rows[0]->name);
        self::assertSame('Doe', $rows[0]->surname);
        self::assertSame('jane@example.com', $rows[0]->email);
    }

    public function testItTreatsMissingRowValuesAsEmptyFields(): void
    {
        $rows = $this->read("name,surname,email\nJohn,Smith\n");

        self::assertSame('', $rows[0]->email);
    }

    public function testItRejectsMissingRequiredHeaders(): void
    {
        $path = $this->csv("name,email\nJohn,john@example.com\n");

        $this->expectException(CsvReadException::class);
        $this->expectExceptionMessage('surname');
        iterator_to_array((new LeagueCsvReader())->read($path));
    }

    public function testItRejectsHeadersThatCollideAfterNormalization(): void
    {
        $path = $this->csv("name,surname,Email,email\nJohn,Smith,first@example.com,second@example.com\n");

        $this->expectException(CsvReadException::class);
        $this->expectExceptionMessage('ambiguous after normalization: email');
        iterator_to_array((new LeagueCsvReader())->read($path));
    }

    /** @return list<RawUserRow> */
    private function read(string $contents): array
    {
        return array_values(iterator_to_array((new LeagueCsvReader())->read($this->csv($contents))));
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'user-import-');
        self::assertIsString($path);
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }
}
