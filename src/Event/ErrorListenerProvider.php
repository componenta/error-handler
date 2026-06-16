<?php

declare(strict_types=1);

namespace Componenta\Error\Event;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Event\ErrorListenerInterface;
use Componenta\Error\Event\ErrorListenerProviderInterface;

/**
 * Error listener provider implementation
 *
 * Manages error listeners with priority-based ordering.
 * Higher priority listeners are invoked first.
 */
final class ErrorListenerProvider implements ErrorListenerProviderInterface
{
    /**
     * @var array<array{listener: ErrorListenerInterface, priority: int}>
     */
    private array $listeners = [];

    private bool $needsSort = false;

    /**
     * Create provider from nullable provider
     *
     * Returns existing provider or creates new empty one.
     *
     * @param ErrorListenerProviderInterface|null $provider Optional provider
     * @return ErrorListenerProviderInterface Provider instance
     */
    public static function createFrom(?ErrorListenerProviderInterface $provider): self
    {
        $new = new self();
        if ($provider !== null) {
            $new->merge($provider);
        }

        return $new;
    }

    /**
     * Add listener with priority
     *
     * Higher priority values mean the listener is called earlier.
     *
     * @param ErrorListenerInterface $listener Listener to add
     * @param int $priority Listener priority
     * @return void
     */
    public function addListener(ErrorListenerInterface $listener, int $priority = 0): void
    {
        foreach ($this->listeners as $item) {
            if ($item['listener'] === $listener) {
                return;
            }
        }

        $this->listeners[] = ['listener' => $listener, 'priority' => $priority];
        $this->needsSort = true;
    }

    /**
     * Get all listeners sorted by priority (descending)
     *
     * @return iterable<ErrorListenerInterface> Listeners in priority order
     */
    public function getListeners(): iterable
    {
        $this->sort();

        foreach ($this->listeners as $item) {
            yield $item['listener'];
        }
    }

    /**
     * Get listeners that support specific exception
     *
     * Filters listeners by calling their supports() method.
     * Returns only listeners that should handle this exception.
     *
     * @param \Throwable $exception Exception to handle
     * @param ErrorContextInterface $context Context information
     * @return array<ErrorListenerInterface>
     */
    public function provideFor(\Throwable $exception, ErrorContextInterface $context): array
    {
        $this->sort();

        $arr = [];
        foreach ($this->listeners as $item) {
            if ($item['listener']->supports($exception, $context)) {
                $arr[] = $item['listener'];
            }
        }

        return $arr;
    }

    /**
     * Merge with another provider
     *
     * Adds all listeners from the given provider to this provider.
     *
     * @param ErrorListenerProviderInterface $provider Provider to merge
     * @return void
     */
    public function merge(ErrorListenerProviderInterface $provider): void
    {
        if ($provider instanceof self) {
            foreach ($provider->listeners as $item) {
                $this->addListener($item['listener'], $item['priority']);
            }

            return;
        }

        foreach ($provider->getListeners() as $listener) {
            $this->addListener($listener);
        }
    }

    /**
     * Sort listeners by priority descending
     */
    private function sort(): void
    {
        if ($this->needsSort) {
            usort(
                $this->listeners,
                static fn(array $a, array $b): int => $b['priority'] <=> $a['priority'],
            );
            $this->needsSort = false;
        }
    }
}
