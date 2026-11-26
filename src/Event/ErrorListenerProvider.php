<?php

declare(strict_types=1);

namespace Componenta\Error\Event;

/** Immutable, ordered snapshot of listeners. Higher priorities run first. */
final readonly class ErrorListenerProvider implements ErrorListenerProviderInterface
{
    /** @var list<ErrorListenerInterface> */
    private array $listeners;

    /**
     * Equal priorities retain declaration order. Repeated listener instances
     * are registered once, at their first declared priority.
     *
     * @param list<ErrorListenerInterface|array{listener: ErrorListenerInterface, priority: int}> $listeners
     */
    public function __construct(array $listeners = [])
    {
        $entries = [];
        $seen = [];
        foreach ($listeners as $entry) {
            $listener = $entry instanceof ErrorListenerInterface ? $entry : $entry['listener'];
            if (isset($seen[spl_object_id($listener)])) {
                continue;
            }
            $seen[spl_object_id($listener)] = true;
            $entries[] = [
                'listener' => $listener,
                'priority' => $entry instanceof ErrorListenerInterface ? 0 : $entry['priority'],
            ];
        }

        usort($entries, static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);
        $this->listeners = array_column($entries, 'listener');
    }

    public function getListeners(): iterable
    {
        yield from $this->listeners;
    }
}
