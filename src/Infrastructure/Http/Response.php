<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * JSON response value object. Only the API front controller calls send().
 */
final class Response
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    private function __construct(
        private readonly int $statusCode,
        private readonly array $payload,
        private readonly array $headers = [],
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        return new self($statusCode, ['data' => $data], $headers);
    }

    /**
     * @param array<string, mixed>  $meta
     * @param array<string, string> $headers
     */
    public static function jsonWithMeta(mixed $data, array $meta, int $statusCode = 200, array $headers = []): self
    {
        return new self($statusCode, ['data' => $data, 'meta' => $meta], $headers);
    }

    /**
     * @param array<string, list<string>> $fields
     * @param array<string, string>       $headers
     */
    public static function error(
        int $statusCode,
        string $code,
        string $message,
        array $fields = [],
        array $headers = [],
    ): self {
        $error = ['code' => $code, 'message' => $message];
        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return new self($statusCode, ['error' => $error], $headers);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
