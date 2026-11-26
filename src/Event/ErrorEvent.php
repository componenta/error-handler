<?php

declare(strict_types=1);

namespace Componenta\Error\Event;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Event\ErrorEventInterface;
use DateTimeImmutable;
use DateTimeZone;
use Componenta\Error\Context\ErrorContextAttribute;
use Throwable;

/**
 * Error event implementation
 *
 * Immutable event containing exception and context information.
 * Dispatched to listeners when exceptions are reported.
 */
final class ErrorEvent implements ErrorEventInterface
{
    /**
     * @param Throwable $exception The exception that occurred
     * @param ErrorContextInterface $context The error context
     * @param DateTimeImmutable $timestamp Event timestamp (defaults to now)
     */
    public readonly ErrorContextInterface $context;

    public ?string $errorId {
        get {
            $id = $this->context->getAttribute(ErrorContextAttribute::ERROR_ID, null);
            return is_string($id) ? $id : null;
        }
    }

    public function __construct(
        public readonly Throwable $exception,
        ErrorContextInterface $context,
        public readonly mixed $result = null,
        ?string $errorId = null,
        public readonly DateTimeImmutable $timestamp = new DateTimeImmutable('now', new DateTimeZone('UTC')),
    ) {
        $this->context = $errorId !== null && $context->getAttribute(ErrorContextAttribute::ERROR_ID, null) !== $errorId
            ? $context->withAttribute(ErrorContextAttribute::ERROR_ID, $errorId)
            : $context;
    }
}
