<?php

declare(strict_types=1);

namespace Componenta\Error\Event\Provider;

use Componenta\Error\Event\ErrorListenerInterface;
use Componenta\Error\Event\ErrorListenerProviderInterface;
use Componenta\Error\Event\Provider\Internal\ListenerIndex;
use InvalidArgumentException;
use Throwable;

/** Immutable explicit registrations grouped by Throwable type. */
final readonly class ArrayErrorListenerProvider implements ErrorListenerProviderInterface
{
    /** @var ListenerIndex<ErrorListenerInterface> */
    private ListenerIndex $index;

    /**
     * @param array<array-key, mixed> $listeners Map from Throwable class names
     * to lists of listeners or ['listener' => ErrorListenerInterface, 'priority' => int].
     */
    public function __construct(array $listeners = [])
    {
        $registrations = [];
        foreach ($listeners as $type => $group) {
            if (!is_string($type) || !is_a($type, Throwable::class, true)) {
                throw new InvalidArgumentException('Error listener groups require Throwable type keys.');
            }
            if (!is_array($group) || !array_is_list($group)) {
                throw new InvalidArgumentException('An error listener group must be a list.');
            }
            foreach ($group as $entry) {
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
                $registrations[] = ['exception' => $type, 'value' => $listener, 'priority' => $priority];
            }
        }
        $this->index = new ListenerIndex($registrations);
    }

    public function provideFor(Throwable $exception): iterable
    {
        $seen = [];
        foreach ($this->index->matching($exception) as $listener) {
            // The first match has the highest matching priority.
            if (!isset($seen[spl_object_id($listener)])) {
                $seen[spl_object_id($listener)] = $listener;
                yield $listener;
            }
        }
    }
}
