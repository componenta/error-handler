<?php

declare(strict_types=1);

namespace Componenta\Error\Event;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Event\ErrorEventInterface;
use DateTimeImmutable;
use Throwable;

/**
 * Error event implementation
 *
 * Immutable event containing exception and context information.
 * Dispatched to listeners when exceptions are reported.
 */
final readonly class ErrorEvent implements ErrorEventInterface
{
    /**
     * @param Throwable $exception The exception that occurred
     * @param ErrorContextInterface $context The error context
     * @param DateTimeImmutable $timestamp Event timestamp (defaults to now)
     */
    public function __construct(
        public Throwable $exception,
        public ErrorContextInterface $context,
        public mixed $result = null,
        public ?string $errorId = null,
        public DateTimeImmutable $timestamp = new DateTimeImmutable()
    ) {
    }
}
