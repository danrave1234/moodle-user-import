<?php

declare(strict_types=1);

namespace App\Import;

use App\Import\Model\RawUserRow;
use App\Import\Model\UserCandidate;

final class UserNormalizer
{
    public function normalize(RawUserRow $row): UserCandidate
    {
        return new UserCandidate(
            name: $this->normalizeName($row->name),
            surname: $this->normalizeName($row->surname),
            email: mb_strtolower(trim($row->email), 'UTF-8'),
        );
    }

    private function normalizeName(string $name): string
    {
        return mb_convert_case(trim($name), MB_CASE_TITLE, 'UTF-8');
    }
}
