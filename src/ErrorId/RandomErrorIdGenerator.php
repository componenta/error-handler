<?php

declare(strict_types=1);

namespace Componenta\Error\ErrorId;

use InvalidArgumentException;

final readonly class RandomErrorIdGenerator implements ErrorIdGeneratorInterface
{
    public function __construct(private string $prefix = 'ERR')
    {
        if (preg_match('/\A[A-Za-z0-9._:-]{1,95}\z/', $prefix) !== 1) {
            throw new InvalidArgumentException('Invalid error identifier prefix.');
        }
    }

    public function generate(): string
    {
        return $this->prefix . '-' . strtoupper(bin2hex(random_bytes(16)));
    }
}
