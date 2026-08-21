<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Import\Model\UserCandidate;
use PDO;
use RuntimeException;

final class PdoUserRepository implements UserRepository
{
    private const LOOKUP_BATCH_SIZE = 1000;

    public function __construct(private PDO $pdo)
    {
    }

    public function findExistingEmails(array $emails): array
    {
        $existing = [];

        foreach (array_chunk(array_values(array_unique($emails)), self::LOOKUP_BATCH_SIZE) as $batch) {
            $placeholders = implode(', ', array_fill(0, count($batch), '?'));
            $statement = $this->pdo->prepare("SELECT email FROM users WHERE email IN ({$placeholders})");
            $statement->execute($batch);

            while (($email = $statement->fetchColumn()) !== false) {
                $existing[] = (string) $email;
            }
        }

        return $existing;
    }

    public function beginTransaction(): void
    {
        if (!$this->pdo->beginTransaction()) {
            throw new RuntimeException('Could not start the import transaction.');
        }
    }

    public function insert(UserCandidate $candidate): bool
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (name, surname, email) VALUES (:name, :surname, :email) '
            . 'ON CONFLICT (email) DO NOTHING',
        );
        $statement->execute([
            'name' => $candidate->name,
            'surname' => $candidate->surname,
            'email' => $candidate->email,
        ]);

        return $statement->rowCount() === 1;
    }

    public function commit(): void
    {
        if (!$this->pdo->commit()) {
            throw new RuntimeException('Could not commit the import transaction.');
        }
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
