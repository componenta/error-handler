<?php

declare(strict_types=1);

namespace Componenta\Error\Context;

/** Distinguishes an omitted attribute default from any explicitly supplied value, including null. */
enum AttributeDefault
{
    case None;
}
