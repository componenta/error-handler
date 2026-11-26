<?php

declare(strict_types=1);

namespace Componenta\Error\ErrorId;

interface ErrorIdGeneratorInterface
{
    public function generate(): string;
}
