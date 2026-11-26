<?php

declare(strict_types=1);

namespace Componenta\Error\Event;

use InvalidArgumentException;

/** Immutable, ordered snapshot of listeners. Higher priorities run first. */
final readonly class ErrorListenerProvider implements ErrorListenerProviderInterface
{
    /** @var list<ErrorListenerInterface> */
    private array $listeners;

    /**
     * Equal priorities retain declaration order. Repeated listener instances
     * are registered once, at their first declared priority.
     *
     * @param array<array-key, mixed> $listeners List of listeners or ['listener' => ErrorListenerInterface, 'priority' => int] registrations.
     */
    public function __construct(array $listeners = [])
    {
        if (!array_is_list($listeners)) {
            throw new InvalidArgumentException('Error listener registrations must be a list.');
        }
        $entries = [];
        $seen = [];
        foreach ($listeners as $entry) {
            if ($entry instanceof ErrorListenerInterface) {
                $listener = $entry;
                $priority = 0;
            } elseif (is_array($entry) && isset($entry['listener'], $entry['priority'])
                && $entry['listener'] instanceof ErrorListenerInterface && is_int($entry['priority'])) {
                $listener = $entry['listener'];
                $priority = $entry['priority'];
            } else {
                throw new InvalidArgumentException('Invalid error listener registration.');
            }
            if (isset($seen[spl_object_id($listener)])) {
                continue;
            }
            $seen[spl_object_id($listener)] = true;
            $entries[] = [
                'listener' => $listener,
                'priority' => $priority,
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
