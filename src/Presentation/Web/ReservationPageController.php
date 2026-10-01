<?php

declare(strict_types=1);

namespace App\Presentation\Web;

use App\Application\Dto\ReservationQuery;
use App\Application\Exception\ValidationException;
use App\Application\ReservationService;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

/**
 * Server-rendered pages (Twig): listing and detail.
 * Interactivity (filtering, creating, cancelling) happens via the JSON API
 * in the browser; these controllers only paint the initial HTML.
 */
final class ReservationPageController
{
    public function __construct(
        private readonly ReservationService $service,
        private readonly \Twig\Environment $twig,
    ) {
    }

    /**
     * GET / — reservation listing with filters.
     */
    public function list(Request $request): Response
    {
        try {
            $query = ReservationQuery::fromArray($request->query());
        } catch (ValidationException) {
            // Broken filters in the URL: fall back to the default view.
            return Response::html('', 302, ['Location' => '/']);
        }

        $page = $this->service->search($query);

        return $this->render('reservations/list.html.twig', [
            'reservations' => $page->items,
            'meta' => [
                'page' => $page->page,
                'limit' => $page->limit,
                'total' => $page->total,
                'totalPages' => $page->totalPages(),
            ],
            'filters' => [
                'status' => $query->status?->value ?? '',
                'from' => $query->from?->format('Y-m-d') ?? '',
                'to' => $query->to?->format('Y-m-d') ?? '',
                'guest' => $query->guest ?? '',
            ],
        ]);
    }

    /**
     * GET /reservations/{id} — detail + audit timeline.
     */
    public function detail(int $reservationId): Response
    {
        $detail = $this->service->getDetail($reservationId);

        return $this->render('reservations/detail.html.twig', [
            'reservation' => $detail->reservation,
            'events' => $detail->events,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context, int $statusCode = 200): Response
    {
        return Response::html($this->twig->render($template, $context), $statusCode);
    }
}
