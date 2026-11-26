<?php

declare(strict_types=1);

namespace Componenta\Error\Internal;

use Closure;
use Componenta\Error\ErrorContextInterface;
use InvalidArgumentException;
use Throwable;

/** @internal Converts all supported filter forms to a single typed predicate. */
final class ErrorPredicate
{
    /**
     * @param class-string<Throwable>|list<class-string<Throwable>>|callable(Throwable, ErrorContextInterface): bool $filter
     * @return Closure(Throwable, ErrorContextInterface): bool
     */
    public static function normalize(string|array|callable $filter): Closure
    {
        if (is_callable($filter)) {
            return static fn (Throwable $error, ErrorContextInterface $context): bool => $filter($error, $context);
        }

        $classes = is_string($filter) ? [$filter] : $filter;
        if (!array_is_list($classes) || !array_all($classes, static fn (mixed $class): bool =>
            is_string($class) && is_a($class, Throwable::class, true))) {
            throw new InvalidArgumentException('Expected a callable or a list of Throwable class names.');
        }

        return static fn (Throwable $error, ErrorContextInterface $context): bool =>
            array_any($classes, static fn (mixed $class): bool => is_string($class) && $error instanceof $class);
    }
}
