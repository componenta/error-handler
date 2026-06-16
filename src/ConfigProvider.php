<?php

declare(strict_types=1);

namespace Componenta\Error;

use Componenta\Config\ConfigProvider as ComponentaProvider;
use Componenta\Error\Handler\CliErrorHandlerInterface;
use Componenta\Error\ErrorId\ErrorIdGeneratorInterface;
use Componenta\Error\Reporter\ErrorReporterInterface;
use Componenta\Error\Handler\HttpErrorHandlerInterface;
use Componenta\Error\Factory\CliErrorHandlerFactory;
use Componenta\Error\Factory\CliListenerProviderFactory;
use Componenta\Error\Factory\HttpErrorResponseGeneratorFactory;
use Componenta\Error\Factory\ErrorIdGeneratorFactory;
use Componenta\Error\Factory\ErrorHandlerMiddlewareFactory;
use Componenta\Error\Factory\ErrorReporterFactory;
use Componenta\Error\Factory\RenderingCliErrorHandlerFactory;
use Componenta\Error\Factory\HttpErrorHandlerFactory;
use Componenta\Error\Factory\HttpListenerProviderFactory;
use Componenta\Error\Http\HttpErrorResponseGenerator;
use Componenta\Error\Handler\CliErrorHandler;
use Componenta\Error\Handler\RenderingCliErrorHandler;
use Componenta\Error\Handler\HttpErrorHandler;
use Componenta\Error\Http\Middleware\ErrorHandlerMiddleware;
use Componenta\Error\Reporter\ErrorReporter;
use Componenta\Error\ErrorId\RandomErrorIdGenerator;

class ConfigProvider extends ComponentaProvider
{
    protected function getFactories(): array
    {
        return [
            HttpErrorHandler::class => HttpErrorHandlerFactory::class,
            HttpErrorResponseGenerator::class => HttpErrorResponseGeneratorFactory::class,
            CliErrorHandler::class => CliErrorHandlerFactory::class,
            RenderingCliErrorHandler::class => RenderingCliErrorHandlerFactory::class,
            ErrorHandlerMiddleware::class => ErrorHandlerMiddlewareFactory::class,
            ErrorReporter::class => ErrorReporterFactory::class,
            RandomErrorIdGenerator::class => ErrorIdGeneratorFactory::class,
            ConfigKey::HTTP_LISTENERS => HttpListenerProviderFactory::class,
            ConfigKey::CLI_LISTENERS => CliListenerProviderFactory::class,
        ];
    }

    protected function getAliases(): array
    {
        return [
            HttpErrorHandlerInterface::class => HttpErrorHandler::class,
            CliErrorHandlerInterface::class => CliErrorHandler::class,
            ErrorReporterInterface::class => ErrorReporter::class,
            ErrorIdGeneratorInterface::class => RandomErrorIdGenerator::class,
            ConfigKey::ERROR_REPORTER => ErrorReporter::class,
            ConfigKey::ERROR_ID_GENERATOR => RandomErrorIdGenerator::class,
            ConfigKey::HTTP_FALLBACK_GENERATOR => HttpErrorResponseGenerator::class,
            ConfigKey::CLI_FALLBACK_HANDLER => RenderingCliErrorHandler::class,
        ];
    }

    protected function getConfig(): array
    {
        return [
            ConfigKey::ERROR_LEVEL => E_ALL,
        ];
    }
}
