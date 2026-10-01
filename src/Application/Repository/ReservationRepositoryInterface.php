<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Application\Dto\ReservationPage;
use App\Application\Dto\ReservationQuery;
use App\Domain\Model\Reservation;
use App\Domain\Model\ReservationEvent;

/**
 * Port through which the Application layer persists reservations.
 * Infrastructure provides the PDO implementation.
 *
 * Reads and writes are separated on purpose: search() is the only method
 * that understands filtering, and write operations are meant to be wrapped
 * by transactional() so that reservation rows and their audit events are
 * always stored together.
 */
interface ReservationRepositoryInterface
{
    public function findById(int $id): ?Reservation;

    /**
     * Audit trail of one reservation, oldest first.
     *
     * @return list<ReservationEvent>
     */
    public function findEvents(int $reservationId): array;

    /**
     * Filtered, paginated listing.
     */
    public function search(ReservationQuery $query): ReservationPage;

    /**
     * INSERTs the reservation and assigns the generated id to the entity.
     */
    public function insert(Reservation $reservation): void;

    /**
     * UPDATEs status and updated_at of an existing reservation.
     */
    public function updateStatus(Reservation $reservation): void;

    /**
     * INSERTs an audit event.
     */
    public function insertEvent(ReservationEvent $event): void;

    /**
     * Runs the given operation atomically (DB transaction in production,
     * synchronous call in test doubles).
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transactional(callable $operation): mixed;
}
