<?php

declare(strict_types=1);

use App\ApplicationServices;
use App\Config\DatabaseConfig;
use App\Database\PdoConnectionFactory;
use App\Database\SchemaManager;
use App\Import\Csv\LeagueCsvReader;
use App\Import\UserImportService;
use App\Import\UserNormalizer;
use App\Import\UserValidator;
use App\Persistence\PdoUserRepository;
use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$pdo = (new PdoConnectionFactory())->create(DatabaseConfig::fromEnvironment());
$repository = new PdoUserRepository($pdo);

return new ApplicationServices(
    new UserImportService(
        new LeagueCsvReader(),
        new UserNormalizer(),
        new UserValidator(),
        $repository,
    ),
    new SchemaManager($pdo, __DIR__ . '/database/schema.sql'),
);
