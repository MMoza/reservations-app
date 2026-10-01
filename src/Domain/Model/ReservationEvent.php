<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\ReservationEventType;
use App\Domain\Enum\ReservationStatus;

/**
 * Immutable audit entry of a reservation (creation or status change).
 * User-facing descriptions are written in Spanish.
 */
final class ReservationEvent
{
    private ?int $id = null;

    private function __construct(
        private int $reservationId,
        private ReservationEventType $type,
        private string $description,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public static function created(int $reservationId): self
    {
        return new self(
            $reservationId,
            ReservationEventType::Created,
            'Reserva creada por el huésped',
            new \DateTimeImmutable(),
        );
    }

    public static function statusChanged(
        int $reservationId,
        ReservationStatus $from,
        ReservationStatus $to,
    ): self {
        return new self(
            $reservationId,
            ReservationEventType::StatusChanged,
            sprintf('Estado cambiado de %s a %s', $from->value, $to->value),
            new \DateTimeImmutable(),
        );
    }

    /**
     * Factory for rows coming from the database.
     */
    public static function hydrate(
        int $id,
        int $reservationId,
        ReservationEventType $type,
        string $description,
        \DateTimeImmutable $createdAt,
    ): self {
        $event = new self($reservationId, $type, $description, $createdAt);
        $event->id = $id;

        return $event;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function reservationId(): int
    {
        return $this->reservationId;
    }

    public function type(): ReservationEventType
    {
        return $this->type;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reservation_id' => $this->reservationId,
            'event_type' => $this->type->value,
            'description' => $this->description,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
