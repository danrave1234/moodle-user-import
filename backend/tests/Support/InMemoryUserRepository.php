<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Import\Model\UserCandidate;
use App\Persistence\UserRepository;
use RuntimeException;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, UserCandidate> */
    private array $users = [];

    /** @var array<string, UserCandidate>|null */
    private ?array $snapshot = null;

    public bool $failOnInsert = false;
    public bool $rejectInserts = false;

    /** @param list<UserCandidate> $users */
    public function __construct(array $users = [])
    {
        foreach ($users as $user) {
            $this->users[$user->email] = $user;
        }
    }

    public function findExistingEmails(array $emails): array
    {
        return array_values(array_filter(
            $emails,
            fn (string $email): bool => isset($this->users[$email]),
        ));
    }

    public function beginTransaction(): void
    {
        $this->snapshot = $this->users;
    }

    public function insert(UserCandidate $candidate): bool
    {
        if ($this->failOnInsert) {
            throw new RuntimeException('Simulated database failure.');
        }

        if ($this->rejectInserts) {
            return false;
        }

        if (isset($this->users[$candidate->email])) {
            return false;
        }

        $this->users[$candidate->email] = $candidate;

        return true;
    }

    public function commit(): void
    {
        $this->snapshot = null;
    }

    public function rollBack(): void
    {
        if ($this->snapshot !== null) {
            $this->users = $this->snapshot;
            $this->snapshot = null;
        }
    }

    public function count(): int
    {
        return count($this->users);
    }
}
