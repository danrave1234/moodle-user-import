<?php

declare(strict_types=1);

namespace App\Import;

use App\Import\Model\UserCandidate;
use App\Import\Model\ValidationError;

final class UserValidator
{
    /** @return list<ValidationError> */
    public function validate(UserCandidate $candidate): array
    {
        $errors = [];

        if ($candidate->name === '') {
            $errors[] = new ValidationError('name', 'required', 'Name is required.');
        }

        if ($candidate->surname === '') {
            $errors[] = new ValidationError('surname', 'required', 'Surname is required.');
        }

        if ($candidate->email === '') {
            $errors[] = new ValidationError('email', 'required', 'Email is required.');
        } elseif (filter_var($candidate->email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = new ValidationError('email', 'invalid_email', 'Enter a valid email address.');
        }

        return $errors;
    }
}
