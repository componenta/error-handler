<?php

declare(strict_types=1);

namespace Componenta\Error;

use Throwable;

interface SupportsErrorInterface
{
    public function supports(Throwable $exception, ErrorContextInterface $context): bool;
}
