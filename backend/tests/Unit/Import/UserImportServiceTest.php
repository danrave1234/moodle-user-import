<?php

declare(strict_types=1);

namespace Tests\Unit\Import;

use App\Import\Csv\LeagueCsvReader;
use App\Import\Model\UserCandidate;
use App\Import\UserImportService;
use App\Import\UserNormalizer;
use App\Import\UserValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\InMemoryUserRepository;

final class UserImportServiceTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            unlink($file);
        }
    }

    public function testItDetectsNormalizedDuplicatesInTheSameFile(): void
    {
        $preview = $this->service()->preview($this->csv(
            "name,surname,email\nJohn,Smith,JOHN@example.com\nJane,Doe,john@example.com\n",
        ));

        self::assertSame(2, $preview->total);
        self::assertSame(1, $preview->valid);
        self::assertSame('duplicate_in_file', $preview->rows[1]->errors[0]->code);
        self::assertSame(3, $preview->rows[1]->rowNumber);
    }

    public function testItDetectsDatabaseDuplicatesInOnePreview(): void
    {
        $repository = new InMemoryUserRepository([
            new UserCandidate('Existing', 'User', 'existing@example.com'),
        ]);

        $preview = $this->service($repository)->preview($this->csv(
            "name,surname,email\nJohn,Smith,existing@example.com\nJane,Doe,new@example.com\n",
        ));

        self::assertSame(1, $preview->valid);
        self::assertSame('duplicate_in_database', $preview->rows[0]->errors[0]->code);
    }

    public function testItRejectsInvalidUsersAndImportsOnlyValidUsers(): void
    {
        $repository = new InMemoryUserRepository();

        $result = $this->service($repository)->import($this->csv(
            "name,surname,email\nJohn,Smith,john@example.com\nJane,Doe,not-an-email\n",
        ));

        self::assertSame(2, $result->total);
        self::assertSame(1, $result->valid);
        self::assertSame(1, $result->invalid);
        self::assertSame(1, $result->imported);
        self::assertSame(1, $result->skipped);
        self::assertSame('john@example.com', $result->importedRows[0]->candidate->email);
        self::assertSame('invalid_email', $result->rejectedRows[0]->errors[0]->code);
        self::assertSame(1, $repository->count());
    }

    public function testItReportsAnInsertConflictAsRejected(): void
    {
        $repository = new InMemoryUserRepository();
        $repository->rejectInserts = true;

        $result = $this->service($repository)->import($this->csv(
            "name,surname,email\nJohn,Smith,john@example.com\n",
        ));

        self::assertSame(0, $result->imported);
        self::assertSame(1, $result->skipped);
        self::assertSame(2, $result->rejectedRows[0]->rowNumber);
        self::assertSame('conflict_during_import', $result->rejectedRows[0]->errors[0]->code);
    }

    public function testPreviewPerformsNoWrites(): void
    {
        $repository = new InMemoryUserRepository();

        $this->service($repository)->preview($this->csv(
            "name,surname,email\nJohn,Smith,john@example.com\n",
        ));

        self::assertSame(0, $repository->count());
    }

    public function testItRollsBackUnexpectedWriteFailures(): void
    {
        $repository = new InMemoryUserRepository();
        $repository->failOnInsert = true;

        try {
            $this->service($repository)->import($this->csv(
                "name,surname,email\nJohn,Smith,john@example.com\n",
            ));
            self::fail('Expected a database failure.');
        } catch (RuntimeException) {
            self::assertSame(0, $repository->count());
        }
    }

    private function service(?InMemoryUserRepository $repository = null): UserImportService
    {
        return new UserImportService(
            new LeagueCsvReader(),
            new UserNormalizer(),
            new UserValidator(),
            $repository ?? new InMemoryUserRepository(),
        );
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
