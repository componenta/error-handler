<?php

declare(strict_types=1);

namespace Componenta\Error\Event;

use Componenta\Error\ErrorContextInterface;
use Throwable;

/**
 * Interface for error listener providers
 *
 * Provides access to registered error listeners with priority support.
 */
interface ErrorListenerProviderInterface
{
    /**
     * Add listener with priority
     *
     * Higher priority listeners are called first.
     *
     * @param ErrorListenerInterface $listener Listener to add
     * @param int $priority Listener priority (higher = earlier)
     * @return void
     */
    public function addListener(ErrorListenerInterface $listener, int $priority = 0): void;

    /**
     * Get all listeners sorted by priority
     *
     * @return iterable<ErrorListenerInterface> Listeners in priority order
     */
    public function getListeners(): iterable;

    /**
     * Get listeners that support specific exception
     *
     * @param Throwable $exception Exception to handle
     * @param ErrorContextInterface $context Context information
     * @return array<ErrorListenerInterface> Listeners that support this exception
     */
    public function provideFor(Throwable $exception, ErrorContextInterface $context): array;
}
