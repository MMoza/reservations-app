<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\Enum\ReservationStatus;

/**
 * Raised when a status transition is not allowed (e.g. confirming an
 * already cancelled reservation). HTTP: 409.
 */
final class InvalidStatusTransitionException extends DomainException
{
    public static function between(ReservationStatus $from, ReservationStatus $to): self
    {
        return new self(sprintf(
            'Cannot change reservation status from %s to %s.',
            $from->value,
            $to->value,
        ));
    }
}
