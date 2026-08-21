<?php

declare(strict_types=1);

namespace App\Http;

use JsonException;
use Psr\Http\Message\ResponseInterface;

final class JsonResponder
{
    /** @param array<string, mixed> $payload */
    public function respond(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            $json = '{"error":{"status":500,"message":"The response could not be encoded."}}';
            $status = 500;
        }

        $response->getBody()->write($json);

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }
}
