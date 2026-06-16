<?php

declare(strict_types=1);

namespace Componenta\Error\Http;

use Componenta\Error\Context\HttpErrorContextInterface;
use Throwable;

interface HttpStatusResolverInterface
{
    public function resolve(Throwable $exception, HttpErrorContextInterface $context): int;
}
