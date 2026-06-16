<?php

declare(strict_types=1);

namespace Componenta\Error\Event;

use Componenta\Error\ErrorContextInterface;
use Throwable;
use DateTimeImmutable;

/**
 * Interface for error events
 *
 * Events are dispatched to listeners when exceptions are reported.
 * Provides access to the exception and context information.
 */
interface ErrorEventInterface
{
    /**
     * Get the exception that triggered the event
     */
    public Throwable $exception { get; }

    /**
     * Get the error context
     */
    public ErrorContextInterface $context { get; }

    /**
     * Get the event timestamp
     */
    public DateTimeImmutable $timestamp { get; }

    /**
     * Get optional handling result, such as an HTTP response
     */
    public mixed $result { get; }

    /**
     * Get optional error ID shared with user-facing output and logs
     */
    public ?string $errorId { get; }
}
