<?php

declare(strict_types=1);

/**
 * Web front controller (server-rendered Twig pages).
 *
 * Routes:
 *   GET /                     → reservation listing
 *   GET /reservations         → reservation listing (alias)
 *   GET /reservations/{id}    → reservation detail + audit timeline
 *
 * Route resolution happens before any database work so that unknown URLs
 * answer 404 even if the database is unavailable.
 */

use App\Application\ReservationService;
use App\Application\Validator\ReservationValidator;
use App\Domain\Exception\ReservationNotFoundException;
use App\Infrastructure\Config\Environment;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Persistence\Database;
use App\Infrastructure\Persistence\PdoReservationRepository;
use App\Infrastructure\Twig\TwigFactory;
use App\Presentation\Web\ReservationPageController;

require dirname(__DIR__) . '/vendor/autoload.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');

Environment::load(dirname(__DIR__));

$twig = TwigFactory::create();
$request = Request::fromGlobals();
$path = $request->path();

$action = 'not_found';
$detailId = null;

if ($path === '/' || $path === '/reservations') {
    $action = 'list';
} elseif (preg_match('#^/reservations/(\d+)$#', $path, $matches) === 1) {
    $action = 'detail';
    $detailId = (int) $matches[1];
}

if ($action === 'not_found') {
    $response = Response::html($twig->render('errors/not_found.html.twig'), 404);
} else {
    try {
        $repository = new PdoReservationRepository(Database::connect());
        $controller = new ReservationPageController(
            new ReservationService($repository, new ReservationValidator()),
            $twig,
        );

        $response = $action === 'list'
            ? $controller->list($request)
            : $controller->detail((int) $detailId);
    } catch (ReservationNotFoundException) {
        $response = Response::html($twig->render('errors/not_found.html.twig'), 404);
    } catch (\Throwable $exception) {
        error_log(sprintf(
            '[web] Unhandled %s: %s in %s:%d',
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
        ));

        $response = Response::html($twig->render('errors/server_error.html.twig'), 500);
    }
}

$response->send();
