<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\HttpInputException;
use App\Http\UploadedCsvValidator;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\UploadedFile;

final class UploadedCsvValidatorTest extends TestCase
{
    private string $path;
    private int $size;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'uploaded-csv-');
        self::assertIsString($path);
        $size = file_put_contents($path, "name,surname,email\nJohn,Smith,john@example.com\n");
        self::assertIsInt($size);
        $this->path = $path;
        $this->size = $size;
    }

    protected function tearDown(): void
    {
        unlink($this->path);
    }

    public function testItReturnsTheTemporaryPathForACsvUpload(): void
    {
        $file = new UploadedFile($this->path, 'users.csv', 'text/csv', $this->size);

        self::assertSame($this->path, (new UploadedCsvValidator())->path($file));
    }

    public function testItRejectsAClientFilenameWithoutCsvExtension(): void
    {
        $file = new UploadedFile($this->path, 'users.txt', 'text/plain', $this->size);

        $this->expectException(HttpInputException::class);
        $this->expectExceptionMessage('.csv');
        (new UploadedCsvValidator())->path($file);
    }

    public function testItRejectsFailedUploads(): void
    {
        $file = new UploadedFile($this->path, 'users.csv', 'text/csv', null, UPLOAD_ERR_PARTIAL);

        $this->expectException(HttpInputException::class);
        (new UploadedCsvValidator())->path($file);
    }
}
