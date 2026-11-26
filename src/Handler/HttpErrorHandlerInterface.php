<?php

declare(strict_types=1);

namespace Componenta\Error\Handler;

use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\SupportsErrorInterface;
use Psr\Http\Message\ResponseInterface;
use Componenta\Error\ErrorOccurrence;

/**
 * Interface for HTTP error handlers
 *
 * Coordinates HTTP exception handling.
 */
interface HttpErrorHandlerInterface extends SupportsErrorInterface
{
    /**
     * Handle exception and create HTTP response
     *
     * @param ErrorOccurrence $error Error occurrence
     * @param HttpErrorContextInterface $context HTTP context
     * @return ResponseInterface PSR-7 response with error content
     */
    public function handle(
        ErrorOccurrence $error,
        HttpErrorContextInterface $context,
    ): ResponseInterface;
}
