<?php

declare(strict_types=1);

namespace Componenta\Error\Http;

interface HttpStatusAwareInterface
{
    public int $statusCode { get; }
}
