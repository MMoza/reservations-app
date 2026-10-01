<?php

declare(strict_types=1);

namespace App\Application\Exception;

/**
 * Raised when client input does not pass server-side validation. HTTP: 400.
 * Errors are grouped by field name so the UI can show them inline.
 */
final class ValidationException extends \RuntimeException
{
    /**
     * @param array<string, list<string>> $errors field name => list of messages
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'Los datos enviados no son válidos.',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
