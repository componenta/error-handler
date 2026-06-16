<?php

declare(strict_types=1);

namespace Componenta\Error\Reporter;

enum ListenerExceptionPolicy
{
    case Swallow;
    case Throw;
}
