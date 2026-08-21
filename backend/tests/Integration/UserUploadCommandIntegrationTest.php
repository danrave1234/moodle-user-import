<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\ApplicationServices;
use App\Console\UserUploadCommand;
use App\Database\SchemaManager;
use App\Import\Csv\LeagueCsvReader;
use App\Import\UserImportService;
use App\Import\UserNormalizer;
use App\Import\UserValidator;
use App\Persistence\PdoUserRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UserUploadCommandIntegrationTest extends TestCase
{
    private PDO $pdo;
    private ApplicationServices $services;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        if (getenv('TEST_DB_HOST') === false) {
            self::markTestSkipped('Set TEST_DB_HOST to run PostgreSQL integration tests.');
        }

        $this->pdo = new PDO(
            sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                getenv('TEST_DB_HOST'),
                getenv('TEST_DB_PORT') ?: '5432',
                getenv('TEST_DB_NAME') ?: 'user_import_test',
            ),
            getenv('TEST_DB_USER') ?: 'user_import',
            getenv('TEST_DB_PASSWORD') ?: 'local_dev_password',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $schema = new SchemaManager($this->pdo, dirname(__DIR__, 2).'/database/schema.sql');
        $schema->rebuild();
        $repository = new PdoUserRepository($this->pdo);
        $this->services = new ApplicationServices(
            new UserImportService(new LeagueCsvReader(), new UserNormalizer(), new UserValidator(), $repository),
            $schema,
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            unlink($file);
        }
    }

    public function testDryRunValidatesWithoutWriting(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute([
            '--file' => $this->csv(),
            '--dry-run' => true,
        ]));
        self::assertSame(0, $this->userCount());
        self::assertStringContainsString('Dry run complete', $tester->getDisplay());
    }

    public function testRegularImportWritesValidRows(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--file' => $this->csv()]));
        self::assertSame(1, $this->userCount());
        self::assertStringContainsString('Imported 1 user', $tester->getDisplay());
    }

    public function testCreateTableRebuildsTheUsersTable(): void
    {
        $this->pdo->exec("INSERT INTO users (name, surname, email) VALUES ('Existing', 'User', 'existing@example.com')");
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--create-table' => true]));
        self::assertSame(0, $this->userCount());
        self::assertStringContainsString('rebuilt successfully', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new UserUploadCommand(fn (): ApplicationServices => $this->services));
    }

    private function csv(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'console-integration-');
        self::assertIsString($path);
        file_put_contents($path, "name,surname,email\nJohn,Smith,john@example.com\nJane,Doe,invalid-email\n");
        $this->files[] = $path;

        return $path;
    }

    private function userCount(): int
    {
        $statement = $this->pdo->query('SELECT COUNT(*) FROM users');
        if ($statement === false) {
            throw new RuntimeException('Could not count test users.');
        }

        return (int) $statement->fetchColumn();
    }
}
