<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\ConfigKey;
use Componenta\Error\Reporter\ErrorReporter;
use Psr\Container\ContainerInterface;

final readonly class CliErrorReporterFactory
{
    public function __invoke(ContainerInterface $container): ErrorReporter
    {
        return (new ErrorReporterFactory(ConfigKey::CLI_LISTENERS))($container);
    }
}
