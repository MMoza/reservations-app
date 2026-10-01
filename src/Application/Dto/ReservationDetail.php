<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Domain\Model\Reservation;
use App\Domain\Model\ReservationEvent;

/**
 * Reservation plus its audit trail, as needed by the detail view.
 */
final class ReservationDetail
{
    /**
     * @param list<ReservationEvent> $events oldest first
     */
    public function __construct(
        public readonly Reservation $reservation,
        public readonly array $events,
    ) {
    }
}
