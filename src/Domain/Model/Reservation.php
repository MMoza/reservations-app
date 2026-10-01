<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\ReservationStatus;
use App\Domain\Exception\InvalidStatusTransitionException;

/**
 * Reservation aggregate root.
 *
 * The entity only knows business rules: which status transitions are legal.
 * Validation of raw input lives in the Application layer, persistence in
 * the Infrastructure layer.
 *
 * Amounts are handled as decimal strings ("480.00") to avoid floating
 * point rounding; the column is DECIMAL(10,2).
 */
final class Reservation
{
    private ?int $id = null;

    private function __construct(
        private string $guestName,
        private string $guestEmail,
        private string $accommodationName,
        private \DateTimeImmutable $checkInDate,
        private \DateTimeImmutable $checkOutDate,
        private ReservationStatus $status,
        private string $amount,
        private ?string $notes,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Factory for a brand new reservation: created_at and updated_at = now.
     */
    public static function create(
        string $guestName,
        string $guestEmail,
        string $accommodationName,
        \DateTimeImmutable $checkInDate,
        \DateTimeImmutable $checkOutDate,
        ReservationStatus $status,
        string $amount,
        ?string $notes,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            $guestName,
            $guestEmail,
            $accommodationName,
            $checkInDate,
            $checkOutDate,
            $status,
            $amount,
            $notes,
            $now,
            $now,
        );
    }

    /**
     * Factory for rows coming from the database (id and timestamps included).
     */
    public static function hydrate(
        int $id,
        string $guestName,
        string $guestEmail,
        string $accommodationName,
        \DateTimeImmutable $checkInDate,
        \DateTimeImmutable $checkOutDate,
        ReservationStatus $status,
        string $amount,
        ?string $notes,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $reservation = new self(
            $guestName,
            $guestEmail,
            $accommodationName,
            $checkInDate,
            $checkOutDate,
            $status,
            $amount,
            $notes,
            $createdAt,
            $updatedAt,
        );
        $reservation->id = $id;

        return $reservation;
    }

    /**
     * Enforces the status state machine and refreshes updated_at.
     *
     * @throws InvalidStatusTransitionException when the transition is not allowed
     */
    public function changeStatus(ReservationStatus $targetStatus): void
    {
        if (!$this->status->canTransitionTo($targetStatus)) {
            throw InvalidStatusTransitionException::between($this->status, $targetStatus);
        }

        $this->status = $targetStatus;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Called by the repository right after INSERT to persist the generated id.
     */
    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new \LogicException('Reservation id has already been assigned.');
        }

        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function guestName(): string
    {
        return $this->guestName;
    }

    public function guestEmail(): string
    {
        return $this->guestEmail;
    }

    public function accommodationName(): string
    {
        return $this->accommodationName;
    }

    public function checkInDate(): \DateTimeImmutable
    {
        return $this->checkInDate;
    }

    public function checkOutDate(): \DateTimeImmutable
    {
        return $this->checkOutDate;
    }

    public function status(): ReservationStatus
    {
        return $this->status;
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * JSON/database representation. Keys mirror the column names.
     *
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'guest_name' => $this->guestName,
            'guest_email' => $this->guestEmail,
            'accommodation_name' => $this->accommodationName,
            'check_in_date' => $this->checkInDate->format('Y-m-d'),
            'check_out_date' => $this->checkOutDate->format('Y-m-d'),
            'status' => $this->status->value,
            'amount' => $this->amount,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
