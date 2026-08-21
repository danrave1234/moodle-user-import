<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Import\Model\UserCandidate;

interface UserRepository
{
    /**
     * @param list<string> $emails
     * @return list<string>
     */
    public function findExistingEmails(array $emails): array;

    public function beginTransaction(): void;

    public function insert(UserCandidate $candidate): bool;

    public function commit(): void;

    public function rollBack(): void;
}
