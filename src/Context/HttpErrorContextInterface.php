<?php

declare(strict_types=1);

namespace Componenta\Error\Context;

use Componenta\Error\ErrorContextInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HTTP-specific error context interface
 *
 * Extends base context with HTTP request information.
 */
interface HttpErrorContextInterface extends ErrorContextInterface
{
    /**
     * Get the HTTP request that caused the error
     */
    public ServerRequestInterface $request { get; }
}
