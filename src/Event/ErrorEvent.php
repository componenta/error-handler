<?php

declare(strict_types=1);

namespace Componenta\Error\Event;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use DateTimeImmutable;

/** Immutable reporting event; time is supplied by the reporter's clock. */
final readonly class ErrorEvent implements ErrorEventInterface
{
    public function __construct(
        public ErrorOccurrence $error,
        public ErrorContextInterface $context,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
