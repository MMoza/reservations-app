<?php

declare(strict_types=1);

namespace App\Application\Validator;

use App\Application\Exception\ValidationException;
use App\Domain\Enum\ReservationStatus;

/**
 * Server-side validation of reservation payloads.
 *
 * Returns the normalized values (trimmed strings, "0.00"-style amounts)
 * or throws a ValidationException with one message per field.
 * The client is never trusted: every rule is enforced here.
 */
final class ReservationValidator
{
    private const MIN_NAME_LENGTH = 2;
    private const MAX_NAME_LENGTH = 120;
    private const MAX_EMAIL_LENGTH = 180;
    private const MAX_NOTES_LENGTH = 1000;

    /**
     * @param array<string, mixed> $input raw decoded JSON body
     *
     * @return array{
     *     guest_name: string,
     *     guest_email: string,
     *     accommodation_name: string,
     *     check_in_date: string,
     *     check_out_date: string,
     *     status: string,
     *     amount: string,
     *     notes: string|null
     * }
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        $errors = [];
        $data = [];

        $guestName = $this->requiredString($input, 'guest_name', $errors);
        if ($guestName !== null) {
            $guestName = $this->checkLength($guestName, 'guest_name', self::MIN_NAME_LENGTH, self::MAX_NAME_LENGTH, 'El nombre del huésped', $errors);
            if ($guestName !== null) {
                $data['guest_name'] = $guestName;
            }
        }

        $guestEmail = $this->requiredString($input, 'guest_email', $errors);
        if ($guestEmail !== null) {
            if (filter_var($guestEmail, FILTER_VALIDATE_EMAIL) === false) {
                $errors['guest_email'][] = 'El email no tiene un formato válido.';
            } elseif (mb_strlen($guestEmail) > self::MAX_EMAIL_LENGTH) {
                $errors['guest_email'][] = sprintf('El email no puede superar %d caracteres.', self::MAX_EMAIL_LENGTH);
            } else {
                $data['guest_email'] = $guestEmail;
            }
        }

        $accommodationName = $this->requiredString($input, 'accommodation_name', $errors);
        if ($accommodationName !== null) {
            $accommodationName = $this->checkLength($accommodationName, 'accommodation_name', self::MIN_NAME_LENGTH, self::MAX_NAME_LENGTH, 'El nombre del alojamiento', $errors);
            if ($accommodationName !== null) {
                $data['accommodation_name'] = $accommodationName;
            }
        }

        $checkInDate = $this->requiredDate($input, 'check_in_date', $errors);
        if ($checkInDate !== null) {
            $today = new \DateTimeImmutable('today');
            if ($checkInDate < $today) {
                $errors['check_in_date'][] = 'La fecha de entrada no puede ser anterior a hoy.';
            } else {
                $data['check_in_date'] = $checkInDate->format('Y-m-d');
            }
        }

        $checkOutDate = $this->requiredDate($input, 'check_out_date', $errors);
        if ($checkOutDate !== null && $checkInDate !== null && $checkOutDate <= $checkInDate) {
            $errors['check_out_date'][] = 'La fecha de salida debe ser posterior a la de entrada.';
        } elseif ($checkOutDate !== null) {
            $data['check_out_date'] = $checkOutDate->format('Y-m-d');
        }

        $data['status'] = $this->optionalStatus($input, $errors);
        $data['amount'] = $this->requiredAmount($input, $errors);
        $data['notes'] = $this->optionalNotes($input, $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        /** @var array{guest_name: string, guest_email: string, accommodation_name: string, check_in_date: string, check_out_date: string, status: string, amount: string, notes: string|null} $data */
        return $data;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, list<string>> $errors
     */
    private function requiredString(array $input, string $key, array &$errors): ?string
    {
        $raw = $input[$key] ?? null;
        if ($raw === null || $raw === '' || !is_scalar($raw)) {
            $errors[$key][] = sprintf('El campo "%s" es obligatorio.', $key);

            return null;
        }

        return trim((string) $raw);
    }

    /**
     * @param array<string, list<string>> $errors
     */
    private function checkLength(
        string $value,
        string $key,
        int $min,
        int $max,
        string $label,
        array &$errors,
    ): ?string {
        $length = mb_strlen($value);
        if ($length < $min) {
            $errors[$key][] = sprintf('%s debe tener al menos %d caracteres.', $label, $min);

            return null;
        }
        if ($length > $max) {
            $errors[$key][] = sprintf('%s no puede superar %d caracteres.', $label, $max);

            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, list<string>> $errors
     */
    private function requiredDate(array $input, string $key, array &$errors): ?\DateTimeImmutable
    {
        $raw = $input[$key] ?? null;
        if ($raw === null || $raw === '' || !is_scalar($raw)) {
            $errors[$key][] = sprintf('El campo "%s" es obligatorio.', $key);

            return null;
        }

        $raw = (string) $raw;
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
     * @param array<string, mixed> $input
     * @param array<string, list<string>> $errors
     */
    private function optionalStatus(array $input, array &$errors): string
    {
        $raw = $input['status'] ?? null;
        if ($raw === null || $raw === '' || !is_scalar($raw)) {
            return ReservationStatus::Pending->value;
        }

        $status = ReservationStatus::tryFrom(strtoupper(trim((string) $raw)));
        if ($status === null) {
            $errors['status'][] = 'Estado no válido. Usa PENDING, CONFIRMED o CANCELLED.';

            return ReservationStatus::Pending->value;
        }

        return $status->value;
    }

    /**
     * Decimal with up to 8 integer digits and max 2 decimals ("480", "480.50").
     * The value is normalized to exactly two decimals.
     *
     * @param array<string, mixed> $input
     * @param array<string, list<string>> $errors
     */
    private function requiredAmount(array $input, array &$errors): string
    {
        $raw = $input['amount'] ?? null;
        if ($raw === null || $raw === '' || !is_scalar($raw)) {
            $errors['amount'][] = 'El campo "amount" es obligatorio.';

            return '0.00';
        }

        $raw = trim((string) $raw);
        if (preg_match('/^\d{1,8}(\.\d{1,2})?$/D', $raw) !== 1) {
            $errors['amount'][] = 'El importe debe ser un número positivo con como máximo 2 decimales.';

            return '0.00';
        }

        [$units, $decimals] = array_pad(explode('.', $raw), 2, '0');
        if ($decimals === '0') {
            $decimals = '00';
        } elseif (mb_strlen($decimals) === 1) {
            $decimals .= '0';
        }

        return $units . '.' . $decimals;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, list<string>> $errors
     */
    private function optionalNotes(array $input, array &$errors): ?string
    {
        $raw = $input['notes'] ?? null;
        if ($raw === null || $raw === '' || !is_scalar($raw)) {
            return null;
        }

        $notes = trim((string) $raw);
        if (mb_strlen($notes) > self::MAX_NOTES_LENGTH) {
            $errors['notes'][] = sprintf('Las notas no pueden superar %d caracteres.', self::MAX_NOTES_LENGTH);

            return null;
        }

        return $notes === '' ? null : $notes;
    }
}
