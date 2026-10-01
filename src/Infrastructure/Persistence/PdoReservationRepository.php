<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Dto\ReservationPage;
use App\Application\Dto\ReservationQuery;
use App\Application\Repository\ReservationRepositoryInterface;
use App\Domain\Enum\ReservationEventType;
use App\Domain\Enum\ReservationStatus;
use App\Domain\Model\Reservation;
use App\Domain\Model\ReservationEvent;

/**
 * PDO implementation of the reservation repository port.
 *
 * Security: every statement is a prepared statement; column names, sort
 * order and operators are hard-coded, only values are bound. User input
 * going into a LIKE pattern has its wildcards escaped so that searching
 * for "50%" does not turn into a wildcard query.
 */
final class PdoReservationRepository implements ReservationRepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
    ) {
    }

    public function findById(int $id): ?Reservation
    {
        $statement = $this->pdo->prepare('SELECT * FROM reservation WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->mapReservation($row);
    }

    public function findEvents(int $reservationId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM reservation_event WHERE reservation_id = :reservation_id ORDER BY created_at ASC, id ASC',
        );
        $statement->execute(['reservation_id' => $reservationId]);

        return array_map(
            fn (array $row): ReservationEvent => $this->mapEvent($row),
            $statement->fetchAll(),
        );
    }

    public function search(ReservationQuery $query): ReservationPage
    {
        $conditions = [];
        $parameters = [];

        if ($query->status !== null) {
            $conditions[] = 'status = :status';
            $parameters['status'] = $query->status->value;
        }

        // Overlap: the stay intersects [from, to].
        if ($query->from !== null) {
            $conditions[] = 'check_out_date >= :from';
            $parameters['from'] = $query->from->format('Y-m-d');
        }
        if ($query->to !== null) {
            $conditions[] = 'check_in_date <= :to';
            $parameters['to'] = $query->to->format('Y-m-d');
        }

        if ($query->guest !== null) {
            $conditions[] = '(guest_name LIKE :guest OR guest_email LIKE :guest)';
            $parameters['guest'] = '%' . addcslashes($query->guest, '%_\\') . '%';
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        $countStatement = $this->pdo->prepare("SELECT COUNT(*) FROM reservation {$where}");
        $countStatement->execute($parameters);
        $total = (int) $countStatement->fetchColumn();

        $listStatement = $this->pdo->prepare(
            "SELECT * FROM reservation {$where} ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset",
        );
        foreach ($parameters as $name => $value) {
            $listStatement->bindValue($name, $value, \PDO::PARAM_STR);
        }
        $listStatement->bindValue('limit', $query->limit, \PDO::PARAM_INT);
        $listStatement->bindValue('offset', $query->offset(), \PDO::PARAM_INT);
        $listStatement->execute();

        $items = array_map(
            fn (array $row): Reservation => $this->mapReservation($row),
            $listStatement->fetchAll(),
        );

        return new ReservationPage($items, $total, $query->page, $query->limit);
    }

    public function insert(Reservation $reservation): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO reservation
                (guest_name, guest_email, accommodation_name, check_in_date, check_out_date,
                 status, amount, notes, created_at, updated_at)
             VALUES
                (:guest_name, :guest_email, :accommodation_name, :check_in_date, :check_out_date,
                 :status, :amount, :notes, :created_at, :updated_at)',
        );

        $statement->execute([
            'guest_name' => $reservation->guestName(),
            'guest_email' => $reservation->guestEmail(),
            'accommodation_name' => $reservation->accommodationName(),
            'check_in_date' => $reservation->checkInDate()->format('Y-m-d'),
            'check_out_date' => $reservation->checkOutDate()->format('Y-m-d'),
            'status' => $reservation->status()->value,
            'amount' => $reservation->amount(),
            'notes' => $reservation->notes(),
            'created_at' => $reservation->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $reservation->updatedAt()->format('Y-m-d H:i:s'),
        ]);

        $reservation->assignId((int) $this->pdo->lastInsertId());
    }

    public function updateStatus(Reservation $reservation): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE reservation SET status = :status, updated_at = :updated_at WHERE id = :id',
        );

        $statement->execute([
            'status' => $reservation->status()->value,
            'updated_at' => $reservation->updatedAt()->format('Y-m-d H:i:s'),
            'id' => $reservation->id(),
        ]);
    }

    public function insertEvent(ReservationEvent $event): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO reservation_event (reservation_id, event_type, description, created_at)
             VALUES (:reservation_id, :event_type, :description, :created_at)',
        );

        $statement->execute([
            'reservation_id' => $event->reservationId(),
            'event_type' => $event->type()->value,
            'description' => $event->description(),
            'created_at' => $event->createdAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function transactional(callable $operation): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $operation();
        }

        $this->pdo->beginTransaction();
        try {
            $result = $operation();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapReservation(array $row): Reservation
    {
        return Reservation::hydrate(
            (int) $row['id'],
            (string) $row['guest_name'],
            (string) $row['guest_email'],
            (string) $row['accommodation_name'],
            new \DateTimeImmutable((string) $row['check_in_date']),
            new \DateTimeImmutable((string) $row['check_out_date']),
            ReservationStatus::from((string) $row['status']),
            (string) $row['amount'],
            $row['notes'] !== null ? (string) $row['notes'] : null,
            new \DateTimeImmutable((string) $row['created_at']),
            new \DateTimeImmutable((string) $row['updated_at']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapEvent(array $row): ReservationEvent
    {
        return ReservationEvent::hydrate(
            (int) $row['id'],
            (int) $row['reservation_id'],
            ReservationEventType::from((string) $row['event_type']),
            (string) $row['description'],
            new \DateTimeImmutable((string) $row['created_at']),
        );
    }
}
