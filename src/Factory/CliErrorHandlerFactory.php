<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\ConfigKey;
use Componenta\Error\Reporter\ErrorReporterInterface;
use Componenta\Error\Handler\CliErrorHandler;
use Componenta\Error\Reporter\ErrorReporter;
use Psr\Container\ContainerInterface;
use Componenta\Error\Internal\TypedService;

final readonly class CliErrorHandlerFactory
{
    public function __invoke(ContainerInterface $container): CliErrorHandler
    {
        $reporter = $container->has(ErrorReporterInterface::class)
            ? TypedService::get($container, ErrorReporterInterface::class, ErrorReporterInterface::class)
            : new ErrorReporter();

        if ($container->has(ConfigKey::CLI_FALLBACK_HANDLER)) {
            return new CliErrorHandler(
                TypedService::get($container, ConfigKey::CLI_FALLBACK_HANDLER, \Componenta\Error\Handler\CliErrorHandlerInterface::class),
                $reporter,
            );
        }

        $renderer = $container->has(ConfigKey::CLI_RENDERER)
            ? TypedService::get($container, ConfigKey::CLI_RENDERER, \Componenta\Error\Renderer\ErrorRendererInterface::class)
            : null;

        return CliErrorHandler::createDefaults($renderer, $reporter);
    }
}
