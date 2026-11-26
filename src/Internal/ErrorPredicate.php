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
     * @param string|array<array-key, mixed>|callable $filter
     * @return Closure(Throwable, ErrorContextInterface): bool
     */
    public static function normalize(string|array|callable $filter): Closure
    {
        if (is_callable($filter)) {
            return static function (Throwable $error, ErrorContextInterface $context) use ($filter): bool {
                $result = $filter($error, $context);
                if (!is_bool($result)) {
                    throw new \UnexpectedValueException('An error predicate must return bool.');
                }
                return $result;
            };
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
