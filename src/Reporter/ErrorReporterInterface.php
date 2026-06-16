<?php

declare(strict_types=1);

namespace Componenta\Error\Reporter;

use Componenta\Error\ErrorContextInterface;
use Throwable;

/**
 * Interface for exception reporting
 *
 * Reporters notify listeners about exceptions for logging, monitoring, etc.
 */
interface ErrorReporterInterface
{
    /**
     * Report exception to listeners
     *
     * @param Throwable $exception Exception to report
     * @param ErrorContextInterface $context Context information
     * @param mixed $result Optional handling result, such as a response
     * @param string|null $errorId Correlation ID shown to the user, when available
     * @return void
     */
    public function report(
        Throwable $exception,
        ErrorContextInterface $context,
        mixed $result = null,
        ?string $errorId = null,
    ): void;
}
