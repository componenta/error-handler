<?php

declare(strict_types=1);

namespace Componenta\Error\Internal;

use Closure;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use InvalidArgumentException;
use Throwable;

/** @internal Normalizes a class filter or callable to the occurrence contract. */
final class ErrorPredicate
{
    /** @return Closure(ErrorOccurrence, ErrorContextInterface): bool */
    public static function normalize(mixed $filter): Closure
    {
        if (is_callable($filter)) {
            $callback = Closure::fromCallable($filter);
            return static function (ErrorOccurrence $error, ErrorContextInterface $context) use ($callback): bool {
                $result = $callback($error, $context);
                if (!is_bool($result)) {
                    throw new \UnexpectedValueException('An error predicate must return bool.');
                }

                return $result;
            };
        }

        $classes = is_string($filter) ? [$filter] : $filter;
        if (!is_array($classes) || !array_is_list($classes) || !array_all($classes, static fn (mixed $class): bool =>
            is_string($class) && is_a($class, Throwable::class, true))) {
            throw new InvalidArgumentException('Expected a callable or a list of Throwable class names.');
        }

        // Detach class-name reference slots after validation. Captured callable
        // object state remains intentionally shared through its closure.
        $snapshot = [];
        foreach ($classes as $class) {
            $snapshot[] = $class;
        }

        return static fn (ErrorOccurrence $error, ErrorContextInterface $context): bool =>
            array_any($snapshot, static fn (mixed $class): bool => is_string($class) && $error->exception instanceof $class);
    }
}
