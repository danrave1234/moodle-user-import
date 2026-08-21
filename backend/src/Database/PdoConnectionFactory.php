<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\DatabaseConfig;
use PDO;

final class PdoConnectionFactory
{
    public function create(DatabaseConfig $config): PDO
    {
        return new PDO(
            "pgsql:host={$config->host};port={$config->port};dbname={$config->name}",
            $config->user,
            $config->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }
}
