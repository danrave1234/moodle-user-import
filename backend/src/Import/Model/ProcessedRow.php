<?php

declare(strict_types=1);

namespace App\Import\Model;

final readonly class ProcessedRow
{
    /** @param list<ValidationError> $errors */
    public function __construct(
        public int $rowNumber,
        public UserCandidate $candidate,
        public array $errors,
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function withError(ValidationError $error): self
    {
        return new self($this->rowNumber, $this->candidate, [...$this->errors, $error]);
    }
}
