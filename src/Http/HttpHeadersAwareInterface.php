<?php

declare(strict_types=1);

namespace Componenta\Error\Http;

/** Optional protocol headers associated with a typed HTTP exception. */
interface HttpHeadersAwareInterface
{
    /** @var array<string, string|list<string>> */
    public array $headers { get; }
}
