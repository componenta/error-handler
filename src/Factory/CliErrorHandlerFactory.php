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

final readonly class CliErrorHandlerFactory
{
    public function __invoke(ContainerInterface $container): CliErrorHandler
    {
        $reporter = $container->has(ConfigKey::ERROR_REPORTER)
            ? $container->get(ConfigKey::ERROR_REPORTER)
            : ($container->has(ErrorReporterInterface::class)
                ? $container->get(ErrorReporterInterface::class)
                : null);

        $errorIdGenerator = $container->has(ConfigKey::ERROR_ID_GENERATOR)
            ? $container->get(ConfigKey::ERROR_ID_GENERATOR)
            : ($container->has(ErrorIdGeneratorInterface::class)
                ? $container->get(ErrorIdGeneratorInterface::class)
                : null);

        if ($container->has(ConfigKey::CLI_FALLBACK_HANDLER)) {
            return new CliErrorHandler(
                $container->get(ConfigKey::CLI_FALLBACK_HANDLER),
                $reporter ?? new ErrorReporter(),
                $errorIdGenerator ?? new RandomErrorIdGenerator(),
            );
        }

        $renderer = $container->has(ConfigKey::CLI_RENDERER)
            ? $container->get(ConfigKey::CLI_RENDERER)
            : null;

        return CliErrorHandler::createDefaults($renderer, $reporter, $errorIdGenerator);
    }
}
