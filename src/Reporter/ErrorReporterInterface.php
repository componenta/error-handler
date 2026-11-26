<?php

declare(strict_types=1);

namespace Componenta\Error\Reporter;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;

interface ErrorReporterInterface
{
    public function report(ErrorOccurrence $error, ErrorContextInterface $context): void;
}
