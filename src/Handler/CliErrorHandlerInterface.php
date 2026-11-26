<?php

declare(strict_types=1);

namespace Componenta\Error\Handler;

use Componenta\Error\Context\CliErrorContextInterface;
use Componenta\Error\SupportsErrorInterface;
use Componenta\Error\ErrorOccurrence;

/**
 * Interface for CLI error handlers
 *
 * Coordinates CLI exception handling.
 */
interface CliErrorHandlerInterface extends SupportsErrorInterface
{
    /**
     * Handle exception in CLI context
     *
     * @param ErrorOccurrence $error Error occurrence
     * @param CliErrorContextInterface $context CLI context with output
     * @return void
     */
    public function handle(
        ErrorOccurrence $error,
        CliErrorContextInterface $context,
    ): void;
}
