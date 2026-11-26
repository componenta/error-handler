<?php

declare(strict_types=1);

namespace Componenta\Error\Reporter\Policy;

use Closure;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Internal\ErrorPredicate;
use InvalidArgumentException;

/** Excludes occurrences matching at least one configured rule. */
final readonly class NonReportableErrors implements ErrorReportingPolicyInterface
{
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
        foreach ($this->filters as $filter) {
            if ($filter($error, $context)) {
                return false;
            }
        }

        return true;
    }
}
