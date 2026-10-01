<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Infrastructure\Http\Exception\BadRequestException;
use App\Infrastructure\Http\Exception\ForbiddenException;

/**
 * Read-only view over the current HTTP request.
 *
 * Mutations (POST/PATCH) must satisfy three independent CSRF defences:
 *   1. Content-Type: application/json  (a plain HTML form cannot send this)
 *   2. X-Requested-With: XMLHttpRequest (custom header => CORS preflight)
 *   3. Same-origin check on the Origin header when the browser sends it
 * Those checks are validated server side; the client JS sets the headers.
 */
final class Request
{
    private const MAX_BODY_BYTES = 32768; // 32 KB

    private ?array $decodedJson = null;

    /**
     * @param array<string, mixed>  $query   raw query-string parameters
     * @param array<string, string> $headers lower-cased header names
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly string $rawBody,
        private readonly array $headers,
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_') && is_string($value)) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $path = self::resolvePath();

        $rawBody = file_get_contents('php://input');
        if ($rawBody === false) {
            $rawBody = '';
        }

        return new self($method, $path, $_GET, $rawBody, $headers);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->query;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * True for POST/PATCH/PUT/DELETE: endpoints that change state.
     */
    public function isMutation(): bool
    {
        return in_array($this->method, ['POST', 'PATCH', 'PUT', 'DELETE'], true);
    }

    /**
     * Enforces the CSRF defences described in the class docblock.
     *
     * @throws ForbiddenException   when origin or headers are not acceptable
     * @throws BadRequestException  when the body is oversized or not JSON
     */
    public function assertMutationAllowed(): void
    {
        $origin = $this->header('origin');
        if ($origin !== null && $origin !== 'null') {
            $originHost = parse_url($origin, PHP_URL_HOST);
            $requestHost = parse_url('http://' . ($this->header('host') ?? ''), PHP_URL_HOST);
            if ($originHost === false || $originHost === null || $requestHost === null || $originHost !== $requestHost) {
                throw new ForbiddenException('Origen no permitido para operaciones de escritura.');
            }
        }

        if ($this->header('x-requested-with') !== 'XMLHttpRequest') {
            throw new ForbiddenException('Cabecera X-Requested-With: XMLHttpRequest requerida.');
        }

        $contentType = (string) $this->header('content-type');
        if (!str_contains(strtolower($contentType), 'application/json')) {
            throw new BadRequestException('Las operaciones de escritura deben enviar Content-Type: application/json.');
        }

        if (strlen($this->rawBody) > self::MAX_BODY_BYTES) {
            throw new BadRequestException('El cuerpo de la petición supera el tamaño máximo de 32 KB.');
        }
    }

    /**
     * Decodes the JSON body into an associative array.
     *
     * @throws BadRequestException when the body is not valid JSON or not an object
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->decodedJson !== null) {
            return $this->decodedJson;
        }

        if (trim($this->rawBody) === '') {
            throw new BadRequestException('Se espera un cuerpo JSON no vacío.');
        }

        try {
            $decoded = json_decode($this->rawBody, false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadRequestException('El cuerpo no es JSON válido: ' . $exception->getMessage());
        }

        if (!is_object($decoded)) {
            throw new BadRequestException('El cuerpo JSON debe ser un objeto.');
        }

        /** @var array<string, mixed> $decoded */
        $this->decodedJson = get_object_vars($decoded);

        return $this->decodedJson;
    }

    /**
     * Resolves the API route path from PATH_INFO or the request URI,
     * so both /api.php/reservations and /api.php work identically.
     */
    private static function resolvePath(): string
    {
        $pathInfo = $_SERVER['PATH_INFO'] ?? null;
        if (is_string($pathInfo) && $pathInfo !== '') {
            $path = $pathInfo;
        } else {
            $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
            $path = str_contains($uri, 'api.php')
                ? (string) preg_replace('#^.*?/api\.php#', '', $uri)
                : $uri;
        }

        if ($path === '' || $path === '/') {
            return '/';
        }

        return rtrim($path, '/');
    }
}
