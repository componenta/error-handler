<?php

declare(strict_types=1);

namespace Componenta\Error\Http;

use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\SupportsErrorInterface;
use Psr\Http\Message\ResponseInterface;
use Componenta\Error\ErrorOccurrence;

/**
 * Produces the final HTTP response for an occurrence.
 *
 * The standard generator produces 4xx/5xx. Explicit custom generators may
 * recover with another final status (200–599, for example a login redirect). They own
 * response semantics, including HEAD, cache policy and representation headers.
 */
interface HttpErrorResponseGeneratorInterface extends SupportsErrorInterface
{
    public function generate(
        ErrorOccurrence $error,
        HttpErrorContextInterface $context,
    ): ResponseInterface;
}
