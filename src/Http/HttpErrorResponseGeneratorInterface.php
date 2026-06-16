<?php

declare(strict_types=1);

namespace Componenta\Error\Http;

use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\SupportsErrorInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

interface HttpErrorResponseGeneratorInterface extends SupportsErrorInterface
{
    public function generate(
        Throwable $exception,
        HttpErrorContextInterface $context,
    ): ResponseInterface;
}
