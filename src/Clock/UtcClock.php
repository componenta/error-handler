<?php

declare(strict_types=1);

namespace Componenta\Error\Clock;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/** System wall clock returning the current time in UTC. */
final readonly class UtcClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
