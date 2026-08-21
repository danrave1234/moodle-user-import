<?php

declare(strict_types=1);

namespace App\Http;

use App\Import\UserImportService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final readonly class PreviewImportAction
{
    public function __construct(
        private UserImportService $service,
        private UploadedCsvValidator $uploadValidator,
        private ImportResponseMapper $mapper,
        private JsonResponder $responder,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $file = $request->getUploadedFiles()['file'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            throw new HttpInputException('Choose a CSV file to preview.');
        }

        $preview = $this->service->preview($this->uploadValidator->path($file));

        return $this->responder->respond($response, $this->mapper->preview($preview));
    }
}
