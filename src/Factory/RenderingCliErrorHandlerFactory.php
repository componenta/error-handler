<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\ConfigKey;
use Componenta\Error\Handler\RenderingCliErrorHandler;
use Componenta\Error\Renderer\SafeCliRenderer;
use Psr\Container\ContainerInterface;
use Componenta\Error\Internal\TypedService;

final readonly class RenderingCliErrorHandlerFactory
{
    public function __invoke(ContainerInterface $container): RenderingCliErrorHandler
    {
        $renderer = $container->has(ConfigKey::CLI_RENDERER)
            ? TypedService::get($container, ConfigKey::CLI_RENDERER, \Componenta\Error\Renderer\ErrorRendererInterface::class)
            : new SafeCliRenderer();

        return new RenderingCliErrorHandler($renderer);
    }
}
