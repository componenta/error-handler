<?php

declare(strict_types=1);

namespace Componenta\Error\Event;

/** Read-only source of listeners in dispatch order. */
interface ErrorListenerProviderInterface
{
    /** @return iterable<ErrorListenerInterface> */
    public function getListeners(): iterable;
}
