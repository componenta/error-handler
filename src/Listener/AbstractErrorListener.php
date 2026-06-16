<?php

declare(strict_types=1);

namespace Componenta\Error\Listener;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Event\ErrorListenerInterface;

/**
 * Base error listener with exception type filtering
 *
 * Override {@see getSupportedExceptions()} to limit listener
 * to specific exception types. Empty array means all exceptions.
 */
abstract readonly class AbstractErrorListener implements ErrorListenerInterface
{
    /**
     * Exception classes this listener handles
     *
     * Empty array means all exceptions are supported.
     *
     * @return array<class-string<\Throwable>>
     */
    protected function getSupportedExceptions(): array
    {
        return [];
    }

    public function supports(\Throwable $exception, ErrorContextInterface $context): bool
    {
        $supported = $this->getSupportedExceptions();

        if ($supported === []) {
            return true;
        }

        return array_any($supported, static fn($class) => $exception instanceof $class);
    }
}
