<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Lifecycle of a reservation. Values match the `reservation.status`
 * database enum exactly (English, upper case).
 */
enum ReservationStatus: string
{
    case Pending = 'PENDING';
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';

    /**
     * Allowed state machine:
     *   PENDING   -> CONFIRMED, CANCELLED
     *   CONFIRMED -> CANCELLED
     *   CANCELLED -> (terminal)
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Confirmed, self::Cancelled], true),
            self::Confirmed => $target === self::Cancelled,
            self::Cancelled => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Confirmed => 'Confirmada',
            self::Cancelled => 'Cancelada',
        };
    }
}
