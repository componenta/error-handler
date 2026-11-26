<?php

declare(strict_types=1);

namespace Componenta\Error;

interface SupportsErrorInterface
{
    public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool;
}
