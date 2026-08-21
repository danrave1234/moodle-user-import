<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\ImportResponseMapper;
use App\Import\Model\ImportPreview;
use App\Import\Model\ProcessedRow;
use App\Import\Model\UserCandidate;
use App\Import\Model\ValidationError;
use PHPUnit\Framework\TestCase;

final class ImportResponseMapperTest extends TestCase
{
    public function testItMapsPreviewRowsAndStructuredErrors(): void
    {
        $preview = new ImportPreview([
            new ProcessedRow(2, new UserCandidate('John', 'Smith', 'john@example.com'), []),
            new ProcessedRow(3, new UserCandidate('Jane', 'Doe', 'invalid'), [
                new ValidationError('email', 'invalid_email', 'Enter a valid email address.'),
            ]),
        ]);

        $payload = (new ImportResponseMapper())->preview($preview);

        self::assertSame(['total' => 2, 'valid' => 1, 'invalid' => 1], $payload['summary']);
        self::assertSame('valid', $payload['rows'][0]['status']);
        self::assertSame('invalid_email', $payload['rows'][1]['errors'][0]['code']);
    }
}
