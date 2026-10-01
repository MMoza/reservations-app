<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Application\Exception\ValidationException;
use App\Domain\Enum\ReservationStatus;

/**
 * Typed, validated representation of the list filters.
 *
 * Date range semantics: a reservation matches when the stay *overlaps*
 * the requested range (check_in_date <= to AND check_out_date >= from).
 */
final class ReservationQuery
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 100;
    public const MAX_GUEST_LENGTH = 120;

    public function __construct(
        public readonly ?ReservationStatus $status = null,
        public readonly ?\DateTimeImmutable $from = null,
        public readonly ?\DateTimeImmutable $to = null,
        public readonly ?string $guest = null,
        public readonly int $page = 1,
        public readonly int $limit = self::DEFAULT_LIMIT,
    ) {
    }

    /**
     * Builds the query from raw query-string parameters.
     *
     * @param array<string, mixed> $params
     *
     * @throws ValidationException when a filter value is not valid
     */
    public static function fromArray(array $params): self
    {
        $errors = [];

        $status = null;
        if (self::hasValue($params, 'status')) {
            $rawStatus = strtoupper(trim((string) $params['status']));
            $status = ReservationStatus::tryFrom($rawStatus);
            if ($status === null) {
                $errors['status'][] = 'Estado no válido. Usa PENDING, CONFIRMED o CANCELLED.';
            }
        }

        $from = self::parseDate($params, 'from', $errors);
        $to = self::parseDate($params, 'to', $errors);
        if ($from !== null && $to !== null && $from > $to) {
            $errors['from'][] = 'La fecha "from" no puede ser posterior a "to".';
        }

        $guest = null;
        if (self::hasValue($params, 'guest')) {
            $guest = trim((string) $params['guest']);
            if (mb_strlen($guest) > self::MAX_GUEST_LENGTH) {
                $errors['guest'][] = sprintf('La búsqueda no puede superar %d caracteres.', self::MAX_GUEST_LENGTH);
                $guest = null;
            } elseif ($guest === '') {
                $guest = null;
            }
        }

        $page = self::parsePositiveInt($params, 'page', $errors) ?? 1;
        $limit = self::parsePositiveInt($params, 'limit', $errors) ?? self::DEFAULT_LIMIT;
        if ($limit > self::MAX_LIMIT) {
            $errors['limit'][] = sprintf('El límite máximo por página es %d.', self::MAX_LIMIT);
            $limit = self::DEFAULT_LIMIT;
        }

        if ($errors !== []) {
            throw new ValidationException($errors, 'Los filtros de búsqueda no son válidos.');
        }

        return new self($status, $from, $to, $guest, $page, $limit);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, list<string>> $errors
     */
    private static function parseDate(array $params, string $key, array &$errors): ?\DateTimeImmutable
    {
        if (!self::hasValue($params, $key)) {
            return null;
        }

        $raw = (string) $params[$key];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $raw) !== 1) {
            $errors[$key][] = sprintf('La fecha "%s" debe tener formato YYYY-MM-DD.', $key);

            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($date === false || $date->format('Y-m-d') !== $raw) {
            $errors[$key][] = sprintf('La fecha "%s" no es una fecha real.', $key);

            return null;
        }

        return $date;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, list<string>> $errors
     */
    private static function parsePositiveInt(array $params, string $key, array &$errors): ?int
    {
        if (!self::hasValue($params, $key)) {
            return null;
        }

        $value = filter_var($params[$key], FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            $errors[$key][] = sprintf('El parámetro "%s" debe ser un entero positivo.', $key);

            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function hasValue(array $params, string $key): bool
    {
        return isset($params[$key]) && is_scalar($params[$key]) && trim((string) $params[$key]) !== '';
    }
}
