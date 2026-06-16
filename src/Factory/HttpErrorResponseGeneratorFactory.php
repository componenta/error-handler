<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\ConfigKey;
use Componenta\Error\Http\HttpErrorResponseGenerator;
use LogicException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

final readonly class HttpErrorResponseGeneratorFactory
{
    public function __invoke(ContainerInterface $container): HttpErrorResponseGenerator
    {
        if (!$container->has(ResponseFactoryInterface::class)) {
            throw new LogicException(sprintf(
                'HTTP error response generation requires "%s". Install and register a PSR-17 integration package.',
                ResponseFactoryInterface::class,
            ));
        }

        $responseFactory = $container->get(ResponseFactoryInterface::class);

        $renderer = $container->has(ConfigKey::HTTP_RENDERER)
            ? $container->get(ConfigKey::HTTP_RENDERER)
            : null;

        return $renderer === null
            ? new HttpErrorResponseGenerator($responseFactory)
            : new HttpErrorResponseGenerator($responseFactory, $renderer);
    }
}
