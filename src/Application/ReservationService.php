<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Dto\ReservationDetail;
use App\Application\Dto\ReservationPage;
use App\Application\Dto\ReservationQuery;
use App\Application\Exception\ValidationException;
use App\Application\Repository\ReservationRepositoryInterface;
use App\Application\Validator\ReservationValidator;
use App\Domain\Enum\ReservationStatus;
use App\Domain\Exception\ReservationNotFoundException;
use App\Domain\Model\Reservation;
use App\Domain\Model\ReservationEvent;

/**
 * Use cases of the reservation management service.
 *
 * Orchestrates validation (input), domain rules (status transitions) and
 * persistence (repository port), including the audit trail: every creation
 * or status change records a reservation_event inside the same transaction.
 */
final class ReservationService
{
    public function __construct(
        private readonly ReservationRepositoryInterface $repository,
        private readonly ReservationValidator $validator,
    ) {
    }

    /**
     * Filtered and paginated listing.
     */
    public function search(ReservationQuery $query): ReservationPage
    {
        return $this->repository->search($query);
    }

    /**
     * @throws ReservationNotFoundException
     */
    public function getDetail(int $reservationId): ReservationDetail
    {
        $reservation = $this->repository->findById($reservationId);
        if ($reservation === null) {
            throw ReservationNotFoundException::withId($reservationId);
        }

        return new ReservationDetail($reservation, $this->repository->findEvents($reservationId));
    }

    /**
     * Creates a reservation (always starts as PENDING unless stated otherwise)
     * together with its CREATED audit event.
     *
     * @param array<string, mixed> $input raw decoded JSON body
     *
     * @throws ValidationException
     */
    public function create(array $input): Reservation
    {
        $data = $this->validator->validate($input);

        $reservation = Reservation::create(
            $data['guest_name'],
            $data['guest_email'],
            $data['accommodation_name'],
            \DateTimeImmutable::createFromFormat('!Y-m-d', $data['check_in_date']),
            \DateTimeImmutable::createFromFormat('!Y-m-d', $data['check_out_date']),
            ReservationStatus::from($data['status']),
            $data['amount'],
            $data['notes'],
        );

        $this->repository->transactional(function () use ($reservation): void {
            $this->repository->insert($reservation);
            $this->repository->insertEvent(ReservationEvent::created((int) $reservation->id()));
        });

        return $reservation;
    }

    /**
     * Confirms or cancels an existing reservation, enforcing the state machine
     * and recording the STATUS_CHANGED audit event atomically.
     *
     * @throws ReservationNotFoundException when the id does not exist (404)
     * @throws ValidationException          when the status value is unknown (400)
     * @throws \App\Domain\Exception\InvalidStatusTransitionException when the
     *         transition is not allowed (409)
     */
    public function changeStatus(int $reservationId, string $newStatus): Reservation
    {
        $targetStatus = ReservationStatus::tryFrom(strtoupper(trim($newStatus)));
        if ($targetStatus === null) {
            throw new ValidationException([
                'status' => ['Estado no válido. Usa PENDING, CONFIRMED o CANCELLED.'],
            ]);
        }

        $reservation = $this->repository->findById($reservationId);
        if ($reservation === null) {
            throw ReservationNotFoundException::withId($reservationId);
        }

        $previousStatus = $reservation->status();
        $reservation->changeStatus($targetStatus); // domain rule, throws 409

        $this->repository->transactional(function () use ($reservation, $previousStatus): void {
            $this->repository->updateStatus($reservation);
            $this->repository->insertEvent(
                ReservationEvent::statusChanged((int) $reservation->id(), $previousStatus, $reservation->status()),
            );
        });

        return $reservation;
    }
}
