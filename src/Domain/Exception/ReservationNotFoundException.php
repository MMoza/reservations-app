<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Raised when a reservation id does not exist. HTTP: 404.
 */
final class ReservationNotFoundException extends DomainException
{
    public static function withId(int $reservationId): self
    {
        return new self(sprintf('Reservation %d not found.', $reservationId));
    }
}
