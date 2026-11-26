<?php

declare(strict_types=1);

namespace Componenta\Error\Reporter\Policy;

use Closure;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Internal\ErrorPredicate;
use Throwable;

/** Excludes occurrences matching at least one configured rule. */
final readonly class NonReportableErrors implements ErrorReportingPolicyInterface
{
    /** @var list<Closure(ErrorOccurrence, ErrorContextInterface): bool> */
    private array $filters;

    /** @param list<class-string<Throwable>|list<class-string<Throwable>>|callable(ErrorOccurrence, ErrorContextInterface): bool> $filters */
    public function __construct(array $filters = [])
    {
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
