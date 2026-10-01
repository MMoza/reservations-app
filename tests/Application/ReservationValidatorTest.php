<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\Exception\ValidationException;
use App\Application\Validator\ReservationValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReservationValidatorTest extends TestCase
{
    private ReservationValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ReservationValidator();
    }

    public function testValidPayloadIsAcceptedAndNormalized(): void
    {
        $data = $this->validator->validate([
            'guest_name' => '  Lucía Fernández  ',
            'guest_email' => 'lucia@example.com',
            'accommodation_name' => 'Hotel Playa de Nerja',
            'check_in_date' => $this->daysFromToday(3),
            'check_out_date' => $this->daysFromToday(7),
            'amount' => '480.5',
            'notes' => '  Llegada tardía.  ',
        ]);

        self::assertSame('Lucía Fernández', $data['guest_name']);
        self::assertSame('480.50', $data['amount']);
        self::assertSame('PENDING', $data['status'], 'status must default to PENDING');
        self::assertSame('Llegada tardía.', $data['notes']);
    }

    public function testRequiredFieldsAreReportedIndividually(): void
    {
        $errors = $this->validationErrorsOf([]);

        self::assertSame(
            ['guest_name', 'guest_email', 'accommodation_name', 'check_in_date', 'check_out_date', 'amount'],
            array_keys($errors),
        );
    }

    public function testInvalidEmailIsRejected(): void
    {
        $payload = $this->validPayload();
        $payload['guest_email'] = 'not-an-email';

        $errors = $this->validationErrorsOf($payload);

        self::assertArrayHasKey('guest_email', $errors);
    }

    public function testCheckOutMustBeAfterCheckIn(): void
    {
        $payload = $this->validPayload();
        $payload['check_in_date'] = $this->daysFromToday(5);
        $payload['check_out_date'] = $this->daysFromToday(5);

        $errors = $this->validationErrorsOf($payload);

        self::assertArrayHasKey('check_out_date', $errors);
    }

    public function testCheckInCannotBeInThePast(): void
    {
        $payload = $this->validPayload();
        $payload['check_in_date'] = $this->daysFromToday(-1);
        $payload['check_out_date'] = $this->daysFromToday(1);

        $errors = $this->validationErrorsOf($payload);

        self::assertArrayHasKey('check_in_date', $errors);
    }

    public function testImpossibleCalendarDateIsRejected(): void
    {
        $payload = $this->validPayload();
        $payload['check_in_date'] = '2026-02-30';

        $errors = $this->validationErrorsOf($payload);

        self::assertArrayHasKey('check_in_date', $errors);
    }

    public function testUnknownStatusIsRejected(): void
    {
        $payload = $this->validPayload();
        $payload['status'] = 'ARCHIVED';

        $errors = $this->validationErrorsOf($payload);

        self::assertArrayHasKey('status', $errors);
    }

    #[DataProvider('invalidAmountProvider')]
    public function testAmountMustBeAPositiveDecimalWithAtMostTwoDecimals(string $amount): void
    {
        $payload = $this->validPayload();
        $payload['amount'] = $amount;

        $errors = $this->validationErrorsOf($payload);

        self::assertArrayHasKey('amount', $errors);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAmountProvider(): iterable
    {
        yield 'negative' => ['-5'];
        yield 'three decimals' => ['12.345'];
        yield 'not a number' => ['abc'];
        yield 'with thousands separator' => ['1,000.50'];
        yield 'too many integer digits' => ['123456789'];
    }

    public function testNotesLengthIsLimited(): void
    {
        $payload = $this->validPayload();
        $payload['notes'] = str_repeat('a', 1001);

        $errors = $this->validationErrorsOf($payload);

        self::assertArrayHasKey('notes', $errors);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, list<string>>
     */
    private function validationErrorsOf(array $payload): array
    {
        try {
            $this->validator->validate($payload);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        self::fail('Expected ValidationException was not thrown.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'guest_name' => 'Carlos Martínez',
            'guest_email' => 'carlos@example.com',
            'accommodation_name' => 'Apartamentos Costa Málaga',
            'check_in_date' => $this->daysFromToday(10),
            'check_out_date' => $this->daysFromToday(14),
            'amount' => '320.00',
        ];
    }

    private function daysFromToday(int $offset): string
    {
        return (new \DateTimeImmutable(sprintf('%+d days', $offset)))->format('Y-m-d');
    }
}
