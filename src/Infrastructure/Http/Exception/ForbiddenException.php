<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Exception;

/**
 * Request rejected before touching business logic (missing anti-CSRF
 * header, cross-origin mutation...). HTTP: 403.
 */
final class ForbiddenException extends \RuntimeException
{
}
