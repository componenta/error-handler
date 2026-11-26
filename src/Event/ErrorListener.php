<?php

declare(strict_types=1);

namespace Componenta\Error\Event;

use Throwable;
use Closure;
use Componenta\Error\Internal\ErrorPredicate;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListenerInterface;

/**
 * Callable-based error listener
 *
 * Wraps a callable to implement ErrorListenerInterface.
 * Supports filtering by exception class or custom callable.
 */
final readonly class ErrorListener implements ErrorListenerInterface
{
    /**
     * @var callable(ErrorEventInterface): void
     */
    private Closure $callback;

    /**
     * @var callable(Throwable, ErrorContextInterface): bool|null
     */
    private ?Closure $supports;

    /**
     * @param callable(ErrorEventInterface): void $callback Listener callback
     * @param class-string<Throwable>|class-string<Throwable>[]|callable(Throwable, ErrorContextInterface): bool|null $supports Exception filter
     */
    private function __construct(
        callable $callback,
        null|string|array|callable $supports = null,
    ) {
        $this->callback = Closure::fromCallable($callback);
        $this->supports = $supports === null ? null : ErrorPredicate::normalize($supports);
    }

    /**
     * Create listener from callable
     *
     * @param callable(ErrorEventInterface): void $callback Listener callback
     * @param class-string<Throwable>|class-string<Throwable>[]|callable(Throwable, ErrorContextInterface): bool|null $supports Exception filter
     * @return self New listener instance
     */
    public static function createFrom(
        callable $callback,
        null|string|array|callable $supports = null,
    ): self {
        return new self($callback, $supports);
    }

    /**
     * Check if listener supports the exception
     *
     * Returns true if no filter configured.
     *
     * @param Throwable $exception Exception to check
     * @param ErrorContextInterface $context Context information
     * @return bool Whether this listener should handle the exception
     */
    public function supports(Throwable $exception, ErrorContextInterface $context): bool
    {
        if ($this->supports !== null) {
            return ($this->supports)($exception, $context);
        }

        return true;
    }

    /**
     * Handle error event
     *
     * @param ErrorEventInterface $event Error event
     */
    public function handleEvent(ErrorEventInterface $event): void
    {
        ($this->callback)($event);
    }
}