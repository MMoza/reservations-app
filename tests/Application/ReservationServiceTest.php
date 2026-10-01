<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\Dto\ReservationQuery;
use App\Application\Exception\ValidationException;
use App\Application\ReservationService;
use App\Application\Validator\ReservationValidator;
use App\Domain\Enum\ReservationStatus;
use App\Domain\Exception\InvalidStatusTransitionException;
use App\Domain\Exception\ReservationNotFoundException;
use App\Tests\Support\FakeReservationRepository;
use PHPUnit\Framework\TestCase;

final class ReservationServiceTest extends TestCase
{
    private FakeReservationRepository $repository;
    private ReservationService $service;

    protected function setUp(): void
    {
        $this->repository = new FakeReservationRepository();
        $this->service = new ReservationService($this->repository, new ReservationValidator());
    }

    public function testCreatePersistsReservationAndRecordsCreationEvent(): void
    {
        $reservation = $this->service->create($this->validPayload());

        self::assertSame(1, $this->repository->reservationCount());
        self::assertSame(ReservationStatus::Pending, $reservation->status());
        self::assertSame(1, $this->repository->eventCount());
        self::assertSame('CREATED', $this->repository->lastEvent()?->type()->value);
        self::assertSame($reservation->id(), $this->repository->lastEvent()?->reservationId());
    }

    public function testCreateRejectsInvalidPayloadAndPersistsNothing(): void
    {
        $payload = $this->validPayload();
        $payload['guest_email'] = 'broken-email';

        try {
            $this->service->create($payload);
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('guest_email', $exception->errors());
        }

        self::assertSame(0, $this->repository->reservationCount());
        self::assertSame(0, $this->repository->eventCount());
    }

    public function testChangeStatusToConfirmedRecordsStatusChangedEvent(): void
    {
        $reservation = $this->service->create($this->validPayload());

        $updated = $this->service->changeStatus((int) $reservation->id(), 'CONFIRMED');

        self::assertSame(ReservationStatus::Confirmed, $updated->status());
        self::assertSame(2, $this->repository->eventCount());
        self::assertSame('STATUS_CHANGED', $this->repository->lastEvent()?->type()->value);
        self::assertSame(
            'Estado cambiado de PENDING a CONFIRMED',
            $this->repository->lastEvent()?->description(),
        );
    }

    public function testChangeStatusAcceptsLowercaseValues(): void
    {
        $reservation = $this->service->create($this->validPayload());

        $updated = $this->service->changeStatus((int) $reservation->id(), 'confirmed');

        self::assertSame(ReservationStatus::Confirmed, $updated->status());
    }

    public function testConfirmingACancelledReservationThrowsConflictAndKeepsState(): void
    {
        $reservation = $this->service->create($this->validPayload());
        $this->service->changeStatus((int) $reservation->id(), 'CANCELLED');

        try {
            $this->service->changeStatus((int) $reservation->id(), 'CONFIRMED');
            self::fail('Expected InvalidStatusTransitionException was not thrown.');
        } catch (InvalidStatusTransitionException) {
            // expected: 409
        }

        self::assertSame(
            ReservationStatus::Cancelled,
            $this->repository->findById((int) $reservation->id())?->status(),
            'status must remain CANCELLED after the rejected transition',
        );
        self::assertSame(2, $this->repository->eventCount(), 'rejected transitions must not record events');
    }

    public function testChangeStatusWithUnknownValueThrowsValidationException(): void
    {
        $reservation = $this->service->create($this->validPayload());

        try {
            $this->service->changeStatus((int) $reservation->id(), 'ARCHIVED');
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('status', $exception->errors());
        }
    }

    public function testGetDetailOfUnknownReservationThrowsNotFound(): void
    {
        $this->expectException(ReservationNotFoundException::class);

        $this->service->getDetail(99999);
    }

    public function testGetDetailReturnsReservationWithItsEvents(): void
    {
        $reservation = $this->service->create($this->validPayload());
        $this->service->changeStatus((int) $reservation->id(), 'CONFIRMED');

        $detail = $this->service->getDetail((int) $reservation->id());

        self::assertSame($reservation->id(), $detail->reservation->id());
        self::assertCount(2, $detail->events);
        self::assertSame('CREATED', $detail->events[0]->type()->value);
        self::assertSame('STATUS_CHANGED', $detail->events[1]->type()->value);
    }

    public function testSearchReturnsReservationPage(): void
    {
        $this->service->create($this->validPayload());
        $this->service->create($this->validPayload());

        $page = $this->service->search(ReservationQuery::fromArray([]));

        self::assertSame(2, $page->total);
        self::assertCount(2, $page->items);
        self::assertSame(1, $page->totalPages());
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'guest_name' => 'Elena Torres',
            'guest_email' => 'elena@example.com',
            'accommodation_name' => 'Hotel Boutique Sevilla Center',
            'check_in_date' => (new \DateTimeImmutable('+7 days'))->format('Y-m-d'),
            'check_out_date' => (new \DateTimeImmutable('+9 days'))->format('Y-m-d'),
            'amount' => '356.00',
            'notes' => null,
        ];
    }
}
