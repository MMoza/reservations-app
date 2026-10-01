<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Exception;

/**
 * Malformed request (invalid JSON, payload too large...). HTTP: 400.
 */
final class BadRequestException extends \RuntimeException
{
}
