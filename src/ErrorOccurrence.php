<?php

declare(strict_types=1);

namespace Componenta\Error;

use InvalidArgumentException;
use Throwable;

/** A single occurrence of an exception, with its mandatory correlation ID. */
final readonly class ErrorOccurrence
{
    public function __construct(
        public Throwable $exception,
        public string $id,
    ) {
        if (preg_match('/\A[A-Za-z0-9._:-]{1,128}\z/', $id) !== 1) {
            throw new InvalidArgumentException('Invalid error occurrence identifier.');
        }
    }
}
