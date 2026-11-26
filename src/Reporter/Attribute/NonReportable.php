<?php

declare(strict_types=1);

namespace Componenta\Error\Reporter\Attribute;

use Attribute;

/** Excludes the directly annotated exception class from standard error reporting. */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class NonReportable
{
}
