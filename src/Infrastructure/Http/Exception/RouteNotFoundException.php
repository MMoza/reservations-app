<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Exception;

/**
 * Unknown API route. HTTP: 404.
 */
final class RouteNotFoundException extends \RuntimeException
{
    public function __construct(string $message = 'Ruta no encontrada.')
    {
        parent::__construct($message);
    }
}
