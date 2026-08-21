<?php

declare(strict_types=1);

namespace App\Import\Model;

final readonly class UserCandidate
{
    public function __construct(
        public string $name,
        public string $surname,
        public string $email,
    ) {
    }
}
