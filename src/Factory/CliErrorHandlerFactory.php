<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\ConfigKey;
use Componenta\Error\ErrorId\ErrorIdGeneratorInterface;
use Componenta\Error\Reporter\ErrorReporterInterface;
use Componenta\Error\ErrorId\RandomErrorIdGenerator;
use Componenta\Error\Handler\CliErrorHandler;
use Componenta\Error\Reporter\ErrorReporter;
use Psr\Container\ContainerInterface;
use Componenta\Error\Internal\TypedService;

final readonly class CliErrorHandlerFactory
{
    public function __invoke(ContainerInterface $container): CliErrorHandler
    {
        $reporter = $container->has(ConfigKey::ERROR_REPORTER)
            ? TypedService::get($container, ConfigKey::ERROR_REPORTER, ErrorReporterInterface::class)
            : ($container->has(ConfigKey::CLI_REPORTER)
                ? TypedService::get($container, ConfigKey::CLI_REPORTER, ErrorReporterInterface::class)
                : ($container->has(ErrorReporterInterface::class) ? TypedService::get($container, ErrorReporterInterface::class, ErrorReporterInterface::class) : null));

        $errorIdGenerator = $container->has(ConfigKey::ERROR_ID_GENERATOR)
            ? TypedService::get($container, ConfigKey::ERROR_ID_GENERATOR, ErrorIdGeneratorInterface::class)
            : ($container->has(ErrorIdGeneratorInterface::class)
                ? TypedService::get($container, ErrorIdGeneratorInterface::class, ErrorIdGeneratorInterface::class)
                : null);

        if ($container->has(ConfigKey::CLI_FALLBACK_HANDLER)) {
            return new CliErrorHandler(
                TypedService::get($container, ConfigKey::CLI_FALLBACK_HANDLER, \Componenta\Error\Handler\CliErrorHandlerInterface::class),
                $reporter ?? new ErrorReporter(),
                $errorIdGenerator ?? new RandomErrorIdGenerator(),
            );
        }

        $renderer = $container->has(ConfigKey::CLI_RENDERER)
            ? TypedService::get($container, ConfigKey::CLI_RENDERER, \Componenta\Error\Renderer\ErrorRendererInterface::class)
            : null;

        return CliErrorHandler::createDefaults($renderer, $reporter, $errorIdGenerator);
    }
}
