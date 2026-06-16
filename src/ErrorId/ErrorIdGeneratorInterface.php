<?php

declare(strict_types=1);

namespace Componenta\Error\ErrorId;

use Componenta\Error\ErrorContextInterface;
use Throwable;

interface ErrorIdGeneratorInterface
{
    public function generate(Throwable $exception, ErrorContextInterface $context): string;
}
