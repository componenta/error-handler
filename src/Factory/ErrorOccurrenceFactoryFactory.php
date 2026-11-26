<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\ErrorId\ErrorIdGeneratorInterface;
use Componenta\Error\ErrorOccurrenceFactory;
use Componenta\Error\Internal\TypedService;
use Psr\Container\ContainerInterface;

final readonly class ErrorOccurrenceFactoryFactory
{
    public function __invoke(ContainerInterface $container): ErrorOccurrenceFactory
    {
        return $container->has(ErrorIdGeneratorInterface::class)
            ? new ErrorOccurrenceFactory(TypedService::get($container, ErrorIdGeneratorInterface::class, ErrorIdGeneratorInterface::class))
            : new ErrorOccurrenceFactory();
    }
}
