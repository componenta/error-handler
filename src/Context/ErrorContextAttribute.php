<?php

declare(strict_types=1);

namespace Componenta\Error\Context;

final class ErrorContextAttribute
{
    public const string HTTP_STATUS_CODE = 'http.status_code';

    private function __construct()
    {
    }
}
