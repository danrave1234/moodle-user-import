<?php

declare(strict_types=1);

namespace App\Http;

use App\ApplicationServices;
use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final readonly class PreviewImportAction
{
    public function __construct(
        private Closure $services,
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

        $preview = $this->services()->userImportService->preview($this->uploadValidator->path($file));

        return $this->responder->respond($response, $this->mapper->preview($preview));
    }

    private function services(): ApplicationServices
    {
        return ($this->services)();
    }
}
