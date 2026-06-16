<?php

declare(strict_types=1);

namespace Componenta\Error\ErrorId;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorId\ErrorIdGeneratorInterface;
use Throwable;

final readonly class RandomErrorIdGenerator implements ErrorIdGeneratorInterface
{
    public function __construct(
        private string $prefix = 'ERR',
    ) {
    }

    public function generate(Throwable $exception, ErrorContextInterface $context): string
    {
        return sprintf(
            '%s-%s-%d',
            $this->prefix,
            strtoupper(bin2hex(random_bytes(4))),
            time(),
        );
    }
}
