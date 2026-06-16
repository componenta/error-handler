<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\ConfigKey;
use Componenta\Error\ErrorId\ErrorIdGeneratorInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Reporter\ErrorReporterInterface;
use Componenta\Error\ErrorId\RandomErrorIdGenerator;
use Componenta\Error\Handler\HttpErrorHandler;
use Componenta\Error\Reporter\ErrorReporter;
use LogicException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

final readonly class HttpErrorHandlerFactory
{
    public function __invoke(ContainerInterface $container): HttpErrorHandler
    {
        $renderer = $container->has(ConfigKey::HTTP_RENDERER)
            ? $container->get(ConfigKey::HTTP_RENDERER)
            : null;

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

        $self = $container->has(ConfigKey::HTTP_FALLBACK_GENERATOR)
            ? new HttpErrorHandler(
                $container->get(ConfigKey::HTTP_FALLBACK_GENERATOR),
                $reporter ?? new ErrorReporter(),
                $errorIdGenerator ?? new RandomErrorIdGenerator(),
            )
            : $this->createDefaultHandler($container, $renderer, $reporter, $errorIdGenerator);

        $config = $container->get('config');
        $generators = $config[ConfigKey::HTTP_GENERATORS] ?? [];

        foreach ($generators as $entryId) {
            $self->addGenerator($container->get($entryId));
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
            $container->get(ResponseFactoryInterface::class),
            $renderer,
            $reporter,
            $errorIdGenerator,
        );
    }
}
