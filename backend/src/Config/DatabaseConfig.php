<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

final readonly class DatabaseConfig
{
    private function __construct(
        public string $host,
        public int $port,
        public string $name,
        public string $user,
        public string $password,
    ) {
    }

    public static function fromEnvironment(): self
    {
        $host = self::required('DB_HOST');
        $port = filter_var(self::required('DB_PORT'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        $name = self::required('DB_NAME');

        if ($port === false) {
            throw new RuntimeException('DB_PORT must be a valid port number.');
        }

        if (preg_match('/\A[a-zA-Z0-9.:-]+\z/', $host) !== 1) {
            throw new RuntimeException('DB_HOST contains unsupported characters.');
        }

        if (preg_match('/\A[a-zA-Z0-9_-]+\z/', $name) !== 1) {
            throw new RuntimeException('DB_NAME contains unsupported characters.');
        }

        return new self($host, $port, $name, self::required('DB_USER'), self::required('DB_PASSWORD'));
    }

    private static function required(string $key): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException("Missing required environment variable: {$key}.");
        }

        return trim($value);
    }
}
