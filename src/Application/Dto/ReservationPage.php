<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Domain\Model\Reservation;

/**
 * One page of the reservation listing (items + total count for pagination).
 */
final class ReservationPage
{
    /**
     * @param list<Reservation> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $limit,
    ) {
    }

    public function totalPages(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return (int) ceil($this->total / $this->limit);
    }
}
