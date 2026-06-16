<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\ConfigKey;
use Componenta\Error\Event\ErrorListenerProvider;
use Componenta\Error\Reporter\ErrorReporter;
use Psr\Container\ContainerInterface;

final readonly class ErrorReporterFactory
{
    public function __invoke(ContainerInterface $container): ErrorReporter
    {
        $listeners = new ErrorListenerProvider();

        if ($container->has(ConfigKey::HTTP_LISTENERS)) {
            $listeners->merge($container->get(ConfigKey::HTTP_LISTENERS));
        }

        if ($container->has(ConfigKey::CLI_LISTENERS)) {
            $listeners->merge($container->get(ConfigKey::CLI_LISTENERS));
        }

        return new ErrorReporter($listeners);
    }
}
