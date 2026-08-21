<?php

declare(strict_types=1);

namespace App;

use App\Database\SchemaManager;
use App\Import\UserImportService;

final readonly class ApplicationServices
{
    public function __construct(
        public UserImportService $userImportService,
        public SchemaManager $schemaManager,
    ) {
    }
}
