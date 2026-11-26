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
        // PHP permits a function and class with the same qualified name.
        // An explicit Throwable type or list of such types denotes a class rule.
        $classes = is_string($filter) ? [$filter] : $filter;
        if (is_array($classes) && array_is_list($classes) && array_all($classes, static fn (mixed $class): bool =>
            is_string($class) && !str_contains($class, '::') && is_a($class, Throwable::class, true))) {
            // Detach class-name reference slots after validation.
            $snapshot = [];
            foreach ($classes as $class) {
                $snapshot[] = $class;
            }

            return static fn (ErrorOccurrence $error, ErrorContextInterface $context): bool =>
                array_any($snapshot, static fn (mixed $class): bool => is_string($class) && $error->exception instanceof $class);
        }

        if (!is_callable($filter)) {
            throw new InvalidArgumentException('Expected a callable or a list of Throwable class names.');
        }

        // Captured callable object state remains intentionally shared.
        $callback = Closure::fromCallable($filter);
        return static function (ErrorOccurrence $error, ErrorContextInterface $context) use ($callback): bool {
            $result = $callback($error, $context);
            if (!is_bool($result)) {
                throw new \UnexpectedValueException('An error predicate must return bool.');
            }

            return $result;
        };
    }
}
