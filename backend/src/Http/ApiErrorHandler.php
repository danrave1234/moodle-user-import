<?php

declare(strict_types=1);

namespace App\Http;

use App\Import\Csv\CsvReadException;
use PDOException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class ApiErrorHandler
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private JsonResponder $responder,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        [$status, $message] = match (true) {
            $exception instanceof HttpInputException => [$exception->status, $exception->getMessage()],
            $exception instanceof CsvReadException => [422, $exception->getMessage()],
            $exception instanceof PDOException => [503, 'The database is temporarily unavailable.'],
            default => [500, 'An unexpected error occurred. Please try again.'],
        };

        return $this->responder->respond($this->responseFactory->createResponse(), [
            'error' => [
                'status' => $status,
                'message' => $message,
            ],
        ], $status);
    }
}
