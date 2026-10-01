<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Enum\ReservationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReservationStatusTest extends TestCase
{
    #[DataProvider('transitionProvider')]
    public function testStateMachine(ReservationStatus $from, ReservationStatus $to, bool $allowed): void
    {
        self::assertSame($allowed, $from->canTransitionTo($to));
    }

    /**
     * @return iterable<string, array{ReservationStatus, ReservationStatus, bool}>
     */
    public static function transitionProvider(): iterable
    {
        yield 'pending -> confirmed' => [ReservationStatus::Pending, ReservationStatus::Confirmed, true];
        yield 'pending -> cancelled' => [ReservationStatus::Pending, ReservationStatus::Cancelled, true];
        yield 'pending -> pending (no-op)' => [ReservationStatus::Pending, ReservationStatus::Pending, false];
        yield 'confirmed -> cancelled' => [ReservationStatus::Confirmed, ReservationStatus::Cancelled, true];
        yield 'confirmed -> pending' => [ReservationStatus::Confirmed, ReservationStatus::Pending, false];
        yield 'confirmed -> confirmed (no-op)' => [ReservationStatus::Confirmed, ReservationStatus::Confirmed, false];
        yield 'cancelled -> pending' => [ReservationStatus::Cancelled, ReservationStatus::Pending, false];
        yield 'cancelled -> confirmed' => [ReservationStatus::Cancelled, ReservationStatus::Confirmed, false];
        yield 'cancelled -> cancelled (no-op)' => [ReservationStatus::Cancelled, ReservationStatus::Cancelled, false];
    }

    public function testValuesMatchDatabaseEnum(): void
    {
        self::assertSame('PENDING', ReservationStatus::Pending->value);
        self::assertSame('CONFIRMED', ReservationStatus::Confirmed->value);
        self::assertSame('CANCELLED', ReservationStatus::Cancelled->value);
    }
}
