<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\UploadedFileInterface;

final class UploadedCsvValidator
{
    public function path(UploadedFileInterface $file): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new HttpInputException('The CSV upload did not complete successfully.');
        }

        $filename = $file->getClientFilename();
        if ($filename === null || mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'csv') {
            throw new HttpInputException('Choose a file with a .csv extension.', 415);
        }

        $path = $file->getStream()->getMetadata('uri');
        if (!is_string($path) || !is_readable($path)) {
            throw new HttpInputException('The uploaded CSV file could not be read.', 422);
        }

        return $path;
    }
}
