<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

final readonly class SchemaManager
{
    public function __construct(
        private PDO $pdo,
        private string $schemaPath,
    ) {
    }

    public function rebuild(): void
    {
        $schema = file_get_contents($this->schemaPath);
        if ($schema === false) {
            throw new RuntimeException('The database schema file could not be read.');
        }

        $this->pdo->exec($schema);
    }
}
