<?php

declare(strict_types=1);

/**
 * API front controller.
 *
 * Run with:  php -S localhost:8000 -t public
 * Routes are resolved from PATH_INFO, e.g. /api.php/reservations/5
 *
 * GET    /api.php/health                    → liveness check (no DB)
 * GET    /api.php/reservations              → list (status, from, to, guest, page, limit)
 * GET    /api.php/reservations/{id}         → detail + audit trail
 * POST   /api.php/reservations              → create (201 + Location)
 * PATCH  /api.php/reservations/{id}/status  → change status (confirm/cancel)
 *
 * Dispatch order: route resolution (404/405) first, then CSRF defences
 * for mutations (403/400), then database work (500 on infrastructure
 * failures, handled centrally by the ExceptionHandler).
 */

use App\Application\ReservationService;
use App\Application\Validator\ReservationValidator;
use App\Infrastructure\Config\Environment;
use App\Infrastructure\Http\ExceptionHandler;
use App\Infrastructure\Http\Exception\MethodNotAllowedException;
use App\Infrastructure\Http\Exception\RouteNotFoundException;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Persistence\Database;
use App\Infrastructure\Persistence\PdoReservationRepository;
use App\Presentation\Api\ReservationController;

require dirname(__DIR__) . '/vendor/autoload.php';

// Never emit PHP warnings/notices into the JSON body.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

Environment::load(dirname(__DIR__));

$handler = new ExceptionHandler();

try {
    $request = Request::fromGlobals();

    // Route table: pattern => HTTP method => controller action.
    $routeTable = [
        ['pattern' => '#^/health$#', 'actions' => ['GET' => 'health']],
        ['pattern' => '#^/reservations$#', 'actions' => ['GET' => 'list', 'POST' => 'create']],
        ['pattern' => '#^/reservations/(\d+)$#', 'actions' => ['GET' => 'show']],
        ['pattern' => '#^/reservations/(\d+)/status$#', 'actions' => ['PATCH' => 'changeStatus']],
    ];

    $path = $request->path();
    $method = $request->isMethod('HEAD') ? 'GET' : $request->method();

    $action = null;
    $actionArgument = null;
    $allowedMethods = [];

    foreach ($routeTable as $route) {
        if (preg_match($route['pattern'], $path, $matches) !== 1) {
            continue;
        }

        $allowedMethods = array_keys($route['actions']);
        if (isset($route['actions'][$method])) {
            $action = $route['actions'][$method];
            $actionArgument = isset($matches[1]) ? (int) $matches[1] : null;
            break;
        }
    }

    if ($action === null) {
        if ($allowedMethods === []) {
            throw new RouteNotFoundException();
        }

        throw new MethodNotAllowedException($allowedMethods);
    }

    if ($request->isMutation()) {
        $request->assertMutationAllowed();
    }

    if ($action === 'health') {
        $response = Response::json(['status' => 'ok']);
    } else {
        $repository = new PdoReservationRepository(Database::connect());
        $controller = new ReservationController(
            new ReservationService($repository, new ReservationValidator()),
        );

        $response = match ($action) {
            'list' => $controller->list($request),
            'create' => $controller->create($request),
            'show' => $controller->show($request, (int) $actionArgument),
            'changeStatus' => $controller->changeStatus($request, (int) $actionArgument),
            default => throw new RouteNotFoundException(),
        };
    }
} catch (\Throwable $exception) {
    $response = $handler->handle($exception);
}

$response->send();
