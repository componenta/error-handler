<?php

declare(strict_types=1);

namespace Componenta\Error\Event;

use Throwable;

/** Supplies matching listeners in dispatch order for the given exception type. */
interface ErrorListenerProviderInterface
{
    /**
     * Matching includes parent exception classes and compatible interfaces.
     * Each call provides an independently traversable sequence.
     *
     * @return iterable<ErrorListenerInterface>
     */
    public function provideFor(Throwable $exception): iterable;
}
