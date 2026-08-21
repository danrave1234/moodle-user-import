<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Database\SchemaManager;
use App\Import\Model\UserCandidate;
use App\Persistence\PdoUserRepository;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class PdoUserRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoUserRepository $repository;

    protected function setUp(): void
    {
        $host = getenv('TEST_DB_HOST');
        if ($host === false) {
            self::markTestSkipped('Set TEST_DB_HOST to run PostgreSQL integration tests.');
        }

        $this->pdo = new PDO(
            sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $host,
                getenv('TEST_DB_PORT') ?: '5432',
                getenv('TEST_DB_NAME') ?: 'user_import_test',
            ),
            getenv('TEST_DB_USER') ?: 'user_import',
            getenv('TEST_DB_PASSWORD') ?: 'local_dev_password',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        (new SchemaManager($this->pdo, dirname(__DIR__, 2) . '/database/schema.sql'))->rebuild();
        $this->repository = new PdoUserRepository($this->pdo);
    }

    public function testItInsertsAndFindsUsers(): void
    {
        self::assertTrue($this->repository->insert(
            new UserCandidate('John', 'Smith', 'john@example.com'),
        ));

        self::assertSame(
            ['john@example.com'],
            $this->repository->findExistingEmails(['john@example.com', 'missing@example.com']),
        );
    }

    public function testDatabaseUniqueConstraintRejectsDuplicates(): void
    {
        $sql = "INSERT INTO users (name, surname, email) VALUES ('John', 'Smith', 'john@example.com')";
        $this->pdo->exec($sql);

        $this->expectException(PDOException::class);
        $this->pdo->exec($sql);
    }

    public function testTransactionRollbackRemovesWrites(): void
    {
        $this->repository->beginTransaction();
        $this->repository->insert(new UserCandidate('John', 'Smith', 'john@example.com'));
        $this->repository->rollBack();

        self::assertSame([], $this->repository->findExistingEmails(['john@example.com']));
    }
}
