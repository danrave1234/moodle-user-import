<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\ApplicationServices;
use App\Http\ApiErrorHandler;
use App\Http\ImportResponseMapper;
use App\Http\JsonResponder;
use App\Http\PreviewImportAction;
use App\Http\UploadedCsvValidator;
use PDOException;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

final class LazyDatabaseErrorHandlingTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'http-import-');
        self::assertIsString($path);
        file_put_contents($path, "name,surname,email\nJohn,Smith,john@example.com\n");
        $this->path = $path;
    }

    protected function tearDown(): void
    {
        unlink($this->path);
    }

    public function testDatabaseFailureDuringLazyResolutionReturnsSanitizedJson(): void
    {
        $responder = new JsonResponder();
        $services = static function (): ApplicationServices {
            throw new PDOException('connection failed for password=top-secret');
        };
        $app = AppFactory::create();
        $app->post('/api/imports/preview', new PreviewImportAction(
            $services,
            new UploadedCsvValidator(),
            new ImportResponseMapper(),
            $responder,
        ));
        $app->addRoutingMiddleware();
        $errorMiddleware = $app->addErrorMiddleware(false, true, true);
        $errorMiddleware->setDefaultErrorHandler(new ApiErrorHandler(new ResponseFactory(), $responder));
        $size = filesize($this->path);
        self::assertIsInt($size);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/imports/preview')
            ->withUploadedFiles([
                'file' => new UploadedFile($this->path, 'users.csv', 'text/csv', $size),
            ]);

        $response = $app->handle($request);
        $body = (string) $response->getBody();

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertJsonStringEqualsJsonString(
            '{"error":{"status":503,"message":"The database is temporarily unavailable."}}',
            $body,
        );
        self::assertStringNotContainsString('top-secret', $body);
        self::assertStringNotContainsString('PDOException', $body);
    }
}
