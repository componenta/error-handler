<?php

declare(strict_types=1);

namespace Componenta\Error;

/** A repeatable capability query, not a dispatch operation or reservation. */
interface SupportsErrorInterface
{
    /** Must not consume the occurrence or perform its handling side effects. */
    public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool;
}
