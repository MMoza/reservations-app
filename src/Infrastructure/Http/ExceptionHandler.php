<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Exception\ValidationException;
use App\Domain\Exception\InvalidStatusTransitionException;
use App\Domain\Exception\ReservationNotFoundException;
use App\Infrastructure\Config\Environment;
use App\Infrastructure\Http\Exception\BadRequestException;
use App\Infrastructure\Http\Exception\ForbiddenException;
use App\Infrastructure\Http\Exception\MethodNotAllowedException;
use App\Infrastructure\Http\Exception\RouteNotFoundException;

/**
 * Centralised error handling: translates every exception thrown while
 * serving a request into the standard JSON error envelope.
 *
 * Unknown exceptions become a generic 500 without leaking stack traces;
 * details go to the server log only.
 */
final class ExceptionHandler
{
    public function handle(\Throwable $exception): Response
    {
        if ($exception instanceof ValidationException) {
            return Response::error(400, 'VALIDATION_ERROR', $exception->getMessage(), $exception->errors());
        }

        if ($exception instanceof BadRequestException) {
            return Response::error(400, 'BAD_REQUEST', $exception->getMessage());
        }

        if ($exception instanceof ReservationNotFoundException) {
            return Response::error(404, 'NOT_FOUND', $exception->getMessage());
        }

        if ($exception instanceof RouteNotFoundException) {
            return Response::error(404, 'NOT_FOUND', $exception->getMessage());
        }

        if ($exception instanceof ForbiddenException) {
            return Response::error(403, 'FORBIDDEN', $exception->getMessage());
        }

        if ($exception instanceof MethodNotAllowedException) {
            return Response::error(
                405,
                'METHOD_NOT_ALLOWED',
                $exception->getMessage(),
                [],
                ['Allow' => implode(', ', $exception->allowedMethods())],
            );
        }

        if ($exception instanceof InvalidStatusTransitionException) {
            return Response::error(409, 'CONFLICT', $exception->getMessage());
        }

        // Unexpected: never expose internals to the client.
        error_log(sprintf(
            '[api] Unhandled %s: %s in %s:%d',
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
        ));

        $message = 'Error interno del servidor.';
        if (Environment::get('APP_DEBUG', '0') === '1') {
            $message .= ' (' . $exception::class . ': ' . $exception->getMessage() . ')';
        }

        return Response::error(500, 'INTERNAL_ERROR', $message);
    }
}
