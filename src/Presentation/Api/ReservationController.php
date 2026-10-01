<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Application\Dto\ReservationQuery;
use App\Application\Exception\ValidationException;
use App\Application\ReservationService;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

/**
 * Thin HTTP layer: translates requests into service calls and service
 * results into JSON responses. No business rules live here.
 */
final class ReservationController
{
    public function __construct(
        private readonly ReservationService $service,
    ) {
    }

    /**
     * GET /reservations — filtered, paginated listing.
     */
    public function list(Request $request): Response
    {
        $query = ReservationQuery::fromArray($request->query());
        $page = $this->service->search($query);

        $items = array_map(
            static fn ($reservation): array => $reservation->toArray(),
            $page->items,
        );

        return Response::jsonWithMeta($items, [
            'page' => $page->page,
            'limit' => $page->limit,
            'total' => $page->total,
            'total_pages' => $page->totalPages(),
        ]);
    }

    /**
     * GET /reservations/{id} — detail with audit trail.
     */
    public function show(Request $request, int $reservationId): Response
    {
        $detail = $this->service->getDetail($reservationId);

        $payload = $detail->reservation->toArray();
        $payload['events'] = array_map(
            static fn ($event): array => $event->toArray(),
            $detail->events,
        );

        return Response::json($payload);
    }

    /**
     * POST /reservations — creates a reservation. Returns 201 + Location.
     */
    public function create(Request $request): Response
    {
        $reservation = $this->service->create($request->json());

        return Response::json(
            $reservation->toArray(),
            201,
            ['Location' => '/api.php/reservations/' . $reservation->id()],
        );
    }

    /**
     * PATCH /reservations/{id}/status — confirms or cancels.
     */
    public function changeStatus(Request $request, int $reservationId): Response
    {
        $body = $request->json();
        $status = $body['status'] ?? null;

        if ($status === null || $status === '') {
            throw new ValidationException(['status' => ['El campo "status" es obligatorio.']]);
        }
        if (!is_string($status)) {
            throw new ValidationException(['status' => ['El campo "status" debe ser una cadena de texto.']]);
        }

        $reservation = $this->service->changeStatus($reservationId, $status);

        return Response::json($reservation->toArray());
    }
}
