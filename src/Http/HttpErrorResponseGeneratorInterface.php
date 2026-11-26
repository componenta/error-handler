<?php

declare(strict_types=1);

namespace Componenta\Error\Http;

use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\SupportsErrorInterface;
use Psr\Http\Message\ResponseInterface;
use Componenta\Error\ErrorOccurrence;

interface HttpErrorResponseGeneratorInterface extends SupportsErrorInterface
{
    public function generate(
        ErrorOccurrence $error,
        HttpErrorContextInterface $context,
    ): ResponseInterface;
}
