<?php

declare(strict_types=1);

namespace Componenta\Error\Reporter\Policy;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;

interface ErrorReportingPolicyInterface
{
    public function shouldReport(ErrorOccurrence $error, ErrorContextInterface $context): bool;
}
