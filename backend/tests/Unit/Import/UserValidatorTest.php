<?php

declare(strict_types=1);

namespace Tests\Unit\Import;

use App\Import\Model\UserCandidate;
use App\Import\UserValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserValidatorTest extends TestCase
{
    public function testItAcceptsAValidCandidate(): void
    {
        self::assertSame([], (new UserValidator())->validate(
            new UserCandidate('John', 'Smith', 'john@example.com'),
        ));
    }

    /** @return iterable<string, array{UserCandidate, string}> */
    public static function invalidCandidates(): iterable
    {
        yield 'missing name' => [new UserCandidate('', 'Smith', 'john@example.com'), 'name'];
        yield 'missing surname' => [new UserCandidate('John', '', 'john@example.com'), 'surname'];
        yield 'missing email' => [new UserCandidate('John', 'Smith', ''), 'email'];
        yield 'invalid email' => [new UserCandidate('John', 'Smith', 'john@example.com@example.com'), 'email'];
    }

    #[DataProvider('invalidCandidates')]
    public function testItRejectsInvalidCandidates(UserCandidate $candidate, string $field): void
    {
        $errors = (new UserValidator())->validate($candidate);

        self::assertNotEmpty($errors);
        self::assertSame($field, $errors[0]->field);
    }
}
