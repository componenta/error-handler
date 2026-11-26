<?php

declare(strict_types=1);

namespace Componenta\Error\Reporter\Policy;

use Closure;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Internal\ErrorPredicate;
use Componenta\Error\Reporter\Attribute\NonReportable;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;

/** Excludes directly marked exception classes and occurrences matching configured rules. */
final readonly class NonReportableErrors implements ErrorReportingPolicyInterface
{
    private const int ATTRIBUTE_CACHE_LIMIT = 256;

    /** @var list<Closure(ErrorOccurrence, ErrorContextInterface): bool> */
    private array $filters;

    /**
     * @param array<array-key, mixed> $filters List of Throwable class names, lists of class names, or boolean predicates.
     * @throws InvalidArgumentException For malformed rule definitions.
     */
    public function __construct(array $filters = [])
    {
        if (!array_is_list($filters)) {
            throw new InvalidArgumentException('Non-reportable error rules must be a list.');
        }
        $this->filters = array_map(ErrorPredicate::normalize(...), $filters);
    }

    public function shouldReport(ErrorOccurrence $error, ErrorContextInterface $context): bool
    {
        if (self::isMarked($error->exception)) {
            return false;
        }

        foreach ($this->filters as $filter) {
            if ($filter($error, $context)) {
                return false;
            }
        }

        return true;
    }

    private static function isMarked(Throwable $exception): bool
    {
        // Class attributes are immutable for the lifetime of the PHP process.
        // Cache only the marker, never contextual reporting decisions.
        /** @var array<class-string<Throwable>, bool> $cache */
        static $cache = [];

        $class = $exception::class;
        if (isset($cache[$class])) {
            return $cache[$class];
        }

        // Reflection returns only attributes declared on the concrete class.
        $attributes = (new ReflectionClass($exception))->getAttributes(NonReportable::class);
        if ($attributes !== []) {
            // Validate the actual PHP attribute declaration (including arguments
            // and repeatability). Invalid metadata must not silently suppress
            // an error; the reporter's existing policy failure boundary applies.
            $attributes[0]->newInstance();
        }

        if (count($cache) >= self::ATTRIBUTE_CACHE_LIMIT) {
            array_shift($cache);
        }

        return $cache[$class] = $attributes !== [];
    }
}
