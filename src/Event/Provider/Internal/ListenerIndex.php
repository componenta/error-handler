<?php

declare(strict_types=1);

namespace Componenta\Error\Event\Provider\Internal;

use Throwable;

/**
 * @internal Sorted routing metadata. Cache contains only immutable registrations,
 * never events, exception objects, support decisions or resolved services.
 *
 * @template T of object|string
 */
final class ListenerIndex
{
    private const int CACHE_LIMIT = 256;

    /** @var list<array{exception: class-string<Throwable>, value: T, priority: int}> */
    private readonly array $registrations;

    /** @var array<class-string<Throwable>, list<T>> */
    private array $cache = [];

    /** @param list<array{exception: class-string<Throwable>, value: T, priority: int}> $registrations */
    public function __construct(array $registrations)
    {
        // PHP sort is stable for equal keys; the original registration order
        // is the tie-breaker across all matching exception types.
        usort($registrations, static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);
        $this->registrations = $registrations;
    }

    /** @return list<T> */
    public function matching(Throwable $exception): array
    {
        $class = $exception::class;
        if (isset($this->cache[$class])) {
            return $this->cache[$class];
        }

        $matched = [];
        $types = [];
        foreach ($this->registrations as $entry) {
            $type = $entry['exception'];
            if ($types[$type] ??= $exception instanceof $type) {
                $matched[] = $entry['value'];
            }
        }

        if (count($this->cache) >= self::CACHE_LIMIT) {
            array_shift($this->cache);
        }

        return $this->cache[$class] = $matched;
    }
}
