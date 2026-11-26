<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\ConfigKey;
use Componenta\Error\ErrorId\ErrorIdGeneratorInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Reporter\ErrorReporterInterface;
use Componenta\Error\ErrorId\RandomErrorIdGenerator;
use Componenta\Error\Handler\HttpErrorHandler;
use Componenta\Error\Http\EmergencyHttpResponseGenerator;
use Componenta\Error\Reporter\ErrorReporter;
use LogicException;
use Psr\Container\ContainerInterface;
use Componenta\Error\Internal\TypedService;
use Componenta\Config\Config;
use Psr\Http\Message\ResponseFactoryInterface;

final readonly class HttpErrorHandlerFactory
{
    public function __invoke(ContainerInterface $container): HttpErrorHandler
    {
        $reporter = $container->has(ConfigKey::ERROR_REPORTER)
            ? TypedService::get($container, ConfigKey::ERROR_REPORTER, ErrorReporterInterface::class)
            : ($container->has(ConfigKey::HTTP_REPORTER)
                ? TypedService::get($container, ConfigKey::HTTP_REPORTER, ErrorReporterInterface::class)
                : ($container->has(ErrorReporterInterface::class) ? TypedService::get($container, ErrorReporterInterface::class, ErrorReporterInterface::class) : null));

        $errorIdGenerator = $container->has(ConfigKey::ERROR_ID_GENERATOR)
            ? TypedService::get($container, ConfigKey::ERROR_ID_GENERATOR, ErrorIdGeneratorInterface::class)
            : ($container->has(ErrorIdGeneratorInterface::class)
                ? TypedService::get($container, ErrorIdGeneratorInterface::class, ErrorIdGeneratorInterface::class)
                : null);

        $self = $container->has(ConfigKey::HTTP_FALLBACK_GENERATOR)
            ? new HttpErrorHandler(
                TypedService::get($container, ConfigKey::HTTP_FALLBACK_GENERATOR, \Componenta\Error\Http\HttpErrorResponseGeneratorInterface::class),
                $reporter ?? new ErrorReporter(),
                $errorIdGenerator ?? new RandomErrorIdGenerator(),
                emergencyGenerator: $container->has(ResponseFactoryInterface::class)
                    ? new EmergencyHttpResponseGenerator(TypedService::get($container, ResponseFactoryInterface::class, ResponseFactoryInterface::class))
                    : null,
            )
            : $this->createDefaultHandler(
                $container,
                $container->has(ConfigKey::HTTP_RENDERER) ? TypedService::get($container, ConfigKey::HTTP_RENDERER, \Componenta\Error\Renderer\ErrorRendererInterface::class) : null,
                $reporter,
                $errorIdGenerator,
            );

        $config = TypedService::get($container, Config::class, Config::class);
        $generators = TypedService::ids($config->array(ConfigKey::HTTP_GENERATORS, []), ConfigKey::HTTP_GENERATORS);

        foreach ($generators as $entryId) {
            $self->addGenerator(TypedService::get($container, $entryId, \Componenta\Error\Http\HttpErrorResponseGeneratorInterface::class));
        }

        return $self;
    }

    private function createDefaultHandler(
        ContainerInterface $container,
        ?ErrorRendererInterface $renderer,
        ?ErrorReporterInterface $reporter,
        ?ErrorIdGeneratorInterface $errorIdGenerator,
    ): HttpErrorHandler {
        if (!$container->has(ResponseFactoryInterface::class)) {
            throw new LogicException(sprintf(
                'HTTP error handling requires "%s" or a configured "%s". Install and register a PSR-17 integration package.',
                ResponseFactoryInterface::class,
                ConfigKey::HTTP_FALLBACK_GENERATOR,
            ));
        }

        return HttpErrorHandler::createDefaults(
            TypedService::get($container, ResponseFactoryInterface::class, ResponseFactoryInterface::class),
            $renderer,
            $reporter,
            $errorIdGenerator,
        );
    }
}
