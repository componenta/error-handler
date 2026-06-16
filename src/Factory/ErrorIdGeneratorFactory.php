<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\ErrorId\RandomErrorIdGenerator;
use Psr\Container\ContainerInterface;

final readonly class ErrorIdGeneratorFactory
{
    public function __invoke(ContainerInterface $container): RandomErrorIdGenerator
    {
        return new RandomErrorIdGenerator();
    }
}
