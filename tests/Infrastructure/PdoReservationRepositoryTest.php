<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure;

use App\Application\Dto\ReservationQuery;
use App\Domain\Enum\ReservationStatus;
use App\Domain\Model\Reservation;
use App\Infrastructure\Config\Environment;
use App\Infrastructure\Persistence\Database;
use App\Infrastructure\Persistence\PdoReservationRepository;
use PHPUnit\Framework\TestCase;

/**
 * Read-only integration tests against the real MySQL database.
 *
 * They self-skip when the database is unavailable (e.g. CI without MySQL):
 * point .env to a database imported from database/schema.sql to run them.
 *
 * The guest/date tests are regression coverage for the search SQL: native
 * prepared statements reject a repeated placeholder (HY093) and an unescaped
 * LIKE wildcard would match every row.
 */
final class PdoReservationRepositoryTest extends TestCase
{
    private static ?PdoReservationRepository $repository = null;
    private static bool $connectionAttempted = false;

    protected function setUp(): void
    {
        if (!self::$connectionAttempted) {
            self::$connectionAttempted = true;
            try {
                Environment::load(dirname(__DIR__, 2));
                $pdo = Database::connect();
                $pdo->query('SELECT 1 FROM reservation LIMIT 1');
                self::$repository = new PdoReservationRepository($pdo);
            } catch (\Throwable) {
                self::$repository = null;
            }
        }

        if (self::$repository === null) {
            $this->markTestSkipped('MySQL no disponible: test de integración omitido.');
        }
    }

    public function testGuestFilterSearchesNameAndEmailWithoutSqlError(): void
    {
        $page = self::$repository->search(ReservationQuery::fromArray(['guest' => 'Luc']));

        self::assertGreaterThanOrEqual(1, $page->total);
        $names = array_map(static fn (Reservation $reservation): string => $reservation->guestName(), $page->items);
        self::assertContains('Lucía Fernández', $names);
    }

    public function testGuestFilterCombinesWithStatus(): void
    {
        $page = self::$repository->search(ReservationQuery::fromArray([
            'guest' => 'Luc',
            'status' => ReservationStatus::Confirmed->value,
        ]));

        self::assertGreaterThanOrEqual(1, $page->total);

        $wrongStatus = [];
        foreach ($page->items as $reservation) {
            if ($reservation->status() !== ReservationStatus::Confirmed) {
                $wrongStatus[] = $reservation->id();
            }
        }
        self::assertSame([], $wrongStatus, 'Solo pueden devolverse reservas CONFIRMED.');
    }

    public function testLikeWildcardsInGuestAreEscaped(): void
    {
        // Without escaping, "%" would match every reservation in the table.
        $page = self::$repository->search(ReservationQuery::fromArray(['guest' => '%']));

        self::assertSame(0, $page->total);
    }

    public function testDateRangeMatchesOverlappingStays(): void
    {
        $all = self::$repository->search(new ReservationQuery(limit: ReservationQuery::MAX_LIMIT));
        self::assertNotEmpty($all->items, 'El seed de schema.sql debe contener reservas.');

        $probe = $all->items[0];
        $from = $probe->checkInDate()->format('Y-m-d');
        $to = $probe->checkOutDate()->format('Y-m-d');

        $page = self::$repository->search(ReservationQuery::fromArray(['from' => $from, 'to' => $to]));

        $ids = array_map(static fn (Reservation $reservation): int => $reservation->id(), $page->items);
        self::assertContains($probe->id(), $ids, 'La reserva de referencia solapa su propio rango.');

        // Violations are aggregated so the assertion count stays fixed
        // regardless of how many reservations the seed (or a user) contains.
        $violations = [];
        foreach ($page->items as $reservation) {
            $checkIn = $reservation->checkInDate()->format('Y-m-d');
            $checkOut = $reservation->checkOutDate()->format('Y-m-d');
            if ($checkOut < $from || $checkIn > $to) {
                $violations[] = sprintf(
                    'id=%d [%s -> %s] no solapa con el rango [%s -> %s]',
                    $reservation->id(),
                    $checkIn,
                    $checkOut,
                    $from,
                    $to,
                );
            }
        }
        self::assertSame([], $violations, 'El filtro de fechas solo debe devolver estancias que solapen el rango.');
    }
}
