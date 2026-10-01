<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Types of audit entries stored in reservation_event.
 * Values match the `reservation_event.event_type` database enum.
 */
enum ReservationEventType: string
{
    case Created = 'CREATED';
    case StatusChanged = 'STATUS_CHANGED';
}
