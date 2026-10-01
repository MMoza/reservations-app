<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Dto\ReservationPage;
use App\Application\Dto\ReservationQuery;
use App\Application\Repository\ReservationRepositoryInterface;
use App\Domain\Model\Reservation;
use App\Domain\Model\ReservationEvent;

/**
 * In-memory double of the repository port used by service tests.
 * Mirrors the persistence behaviour (id assignment, ordering) without a DB.
 */
final class FakeReservationRepository implements ReservationRepositoryInterface
{
    /** @var array<int, Reservation> */
    private array $reservations = [];

    /** @var list<ReservationEvent> */
    private array $events = [];

    private int $nextId = 1;

    public function findById(int $id): ?Reservation
    {
        return $this->reservations[$id] ?? null;
    }

    public function findEvents(int $reservationId): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (ReservationEvent $event): bool => $event->reservationId() === $reservationId,
        ));
    }

    public function search(ReservationQuery $query): ReservationPage
    {
        $items = array_values($this->reservations);
        $total = count($items);
        $items = array_slice($items, $query->offset(), $query->limit);

        return new ReservationPage($items, $total, $query->page, $query->limit);
    }

    public function insert(Reservation $reservation): void
    {
        $reservation->assignId($this->nextId++);
        $this->reservations[$reservation->id()] = $reservation;
    }

    public function updateStatus(Reservation $reservation): void
    {
        $this->reservations[$reservation->id()] = $reservation;
    }

    public function insertEvent(ReservationEvent $event): void
    {
        $this->events[] = $event;
    }

    public function transactional(callable $operation): mixed
    {
        return $operation();
    }

    public function reservationCount(): int
    {
        return count($this->reservations);
    }

    public function eventCount(): int
    {
        return count($this->events);
    }

    public function lastEvent(): ?ReservationEvent
    {
        return $this->events === [] ? null : $this->events[array_key_last($this->events)];
    }
}
