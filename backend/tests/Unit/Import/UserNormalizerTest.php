<?php

declare(strict_types=1);

namespace Tests\Unit\Import;

use App\Import\Model\RawUserRow;
use App\Import\UserNormalizer;
use PHPUnit\Framework\TestCase;

final class UserNormalizerTest extends TestCase
{
    public function testItTrimsAndNormalizesUserFields(): void
    {
        $candidate = (new UserNormalizer())->normalize(
            new RawUserRow(2, '  jOhN  ', ' smITH ', ' JOHN@Example.COM '),
        );

        self::assertSame('John', $candidate->name);
        self::assertSame('Smith', $candidate->surname);
        self::assertSame('john@example.com', $candidate->email);
    }

    public function testItNormalizesMultibyteNames(): void
    {
        $candidate = (new UserNormalizer())->normalize(
            new RawUserRow(2, 'ÉLODIE', 'NÚÑEZ', 'elodie@example.com'),
        );

        self::assertSame('Élodie', $candidate->name);
        self::assertSame('Núñez', $candidate->surname);
    }
}
