<?php

declare(strict_types=1);

namespace App\Import\Model;

final readonly class RawUserRow
{
    public function __construct(
        public int $rowNumber,
        public string $name,
        public string $surname,
        public string $email,
    ) {
    }
}
