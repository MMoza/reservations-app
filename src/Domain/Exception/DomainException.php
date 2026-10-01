<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Base class for every rule broken by the outside world.
 * The HTTP layer translates these exceptions into status codes.
 */
abstract class DomainException extends \RuntimeException
{
}
