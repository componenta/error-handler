<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\ConfigKey;
use Componenta\Error\Handler\RenderingCliErrorHandler;
use Componenta\Error\Renderer\PlainTextRenderer;
use Psr\Container\ContainerInterface;

final readonly class RenderingCliErrorHandlerFactory
{
    public function __invoke(ContainerInterface $container): RenderingCliErrorHandler
    {
        $renderer = $container->has(ConfigKey::CLI_RENDERER)
            ? $container->get(ConfigKey::CLI_RENDERER)
            : new PlainTextRenderer();

        return new RenderingCliErrorHandler($renderer);
    }
}
