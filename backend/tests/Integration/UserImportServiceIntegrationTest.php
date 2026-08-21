<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Database\SchemaManager;
use App\Import\Csv\LeagueCsvReader;
use App\Import\Model\ImportResult;
use App\Import\UserImportService;
use App\Import\UserNormalizer;
use App\Import\UserValidator;
use App\Persistence\PdoUserRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UserImportServiceIntegrationTest extends TestCase
{
    private PDO $pdo;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        $host = getenv('TEST_DB_HOST');
        if ($host === false) {
            self::markTestSkipped('Set TEST_DB_HOST to run PostgreSQL integration tests.');
        }

        $this->pdo = new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, getenv('TEST_DB_PORT') ?: '5432', getenv('TEST_DB_NAME') ?: 'user_import_test'),
            getenv('TEST_DB_USER') ?: 'user_import',
            getenv('TEST_DB_PASSWORD') ?: 'local_dev_password',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        (new SchemaManager($this->pdo, dirname(__DIR__, 2).'/database/schema.sql'))->rebuild();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            unlink($file);
        }
    }

    public function testSuccessfulImportMatchesNormalizedDatabaseRows(): void
    {
        $result = $this->import("name,surname,email\njohn,smith,JOHN@EXAMPLE.COM\njane,doe,jane@example.com\n");

        self::assertSame(2, $result->imported);
        self::assertSame([], $result->rejectedRows);
        self::assertSame([
            ['John', 'Smith', 'john@example.com'],
            ['Jane', 'Doe', 'jane@example.com'],
        ], $this->databaseUsers());
        self::assertSame('john@example.com', $result->importedRows[0]->candidate->email);
    }

    public function testInvalidRecordIsRejectedAndNotPersisted(): void
    {
        $result = $this->import("name,surname,email\njohn,smith,JOHN@EXAMPLE.COM\njane,doe,invalid-email\n");

        self::assertSame(1, $result->imported);
        self::assertCount(1, $result->rejectedRows);
        self::assertSame(3, $result->rejectedRows[0]->rowNumber);
        self::assertSame('invalid_email', $result->rejectedRows[0]->errors[0]->code);
        self::assertSame([['John', 'Smith', 'john@example.com']], $this->databaseUsers());
    }

    public function testExistingDatabaseUserIsRejectedWhileNewUserIsInserted(): void
    {
        $this->pdo->exec("INSERT INTO users (name, surname, email) VALUES ('John', 'Smith', 'john@example.com')");

        $result = $this->import("name,surname,email\njohn,smith,JOHN@EXAMPLE.COM\njane,doe,JANE@EXAMPLE.COM\n");

        self::assertSame(1, $result->imported);
        self::assertSame('jane@example.com', $result->importedRows[0]->candidate->email);
        self::assertSame('duplicate_in_database', $result->rejectedRows[0]->errors[0]->code);
        self::assertSame([
            ['John', 'Smith', 'john@example.com'],
            ['Jane', 'Doe', 'jane@example.com'],
        ], $this->databaseUsers());
    }

    public function testLaterDuplicateInFileIsRejected(): void
    {
        $result = $this->import("name,surname,email\njohn,smith,JOHN@EXAMPLE.COM\njohn,smith,john@example.com\n");

        self::assertSame(1, $result->imported);
        self::assertSame('duplicate_in_file', $result->rejectedRows[0]->errors[0]->code);
        self::assertSame([['John', 'Smith', 'john@example.com']], $this->databaseUsers());
    }

    private function import(string $contents): ImportResult
    {
        $path = tempnam(sys_get_temp_dir(), 'user-import-integration-');
        self::assertIsString($path);
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return (new UserImportService(
            new LeagueCsvReader(),
            new UserNormalizer(),
            new UserValidator(),
            new PdoUserRepository($this->pdo),
        ))->import($path);
    }

    /** @return list<array{string, string, string}> */
    private function databaseUsers(): array
    {
        $statement = $this->pdo->query('SELECT name, surname, email FROM users ORDER BY id');
        if ($statement === false) {
            throw new RuntimeException('Could not query imported users.');
        }
        $rows = $statement->fetchAll(PDO::FETCH_NUM);

        return array_values(array_map(
            static fn (array $row): array => [(string) $row[0], (string) $row[1], (string) $row[2]],
            $rows,
        ));
    }
}
