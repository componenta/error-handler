<?php

declare(strict_types=1);

namespace Componenta\Error\Event;

use Componenta\Error\SupportsErrorInterface;

/**
 * Interface for error event listeners
 *
 * Listeners are notified when exceptions are reported.
 * Used for logging, monitoring, notifications, etc.
 */
interface ErrorListenerInterface extends SupportsErrorInterface
{
    /**
     * Handle error event
     *
     * @param ErrorEventInterface $event Error event with exception and context
     * @return void
     */
    public function handleEvent(ErrorEventInterface $event): void;
}
