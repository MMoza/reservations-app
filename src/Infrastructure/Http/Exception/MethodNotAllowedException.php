<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Exception;

/**
 * Known path with an unsupported HTTP method. HTTP: 405.
 */
final class MethodNotAllowedException extends \RuntimeException
{
    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(
        private readonly array $allowedMethods,
        string $message = 'Método HTTP no permitido para este recurso.',
    ) {
        parent::__construct($message);
    }

    /**
     * @return list<string>
     */
    public function allowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
