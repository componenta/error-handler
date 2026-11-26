<?php

declare(strict_types=1);

namespace Componenta\Error\Internal;

use Componenta\Error\Event\ErrorListenerInterface;
use UnexpectedValueException;

/** @internal Runtime validation of the PHPDoc-typed iterable boundary. */
final class ListenerBoundary
{
    public static function requireListener(mixed $listener): ErrorListenerInterface
    {
        if (!$listener instanceof ErrorListenerInterface) {
            throw new UnexpectedValueException('Provider must yield error listeners.');
        }

        return $listener;
    }
}
