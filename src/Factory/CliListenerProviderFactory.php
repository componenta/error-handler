<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\ConfigKey;
use Componenta\Error\Event\ErrorListenerProvider;
use Psr\Container\ContainerInterface;

final readonly class CliListenerProviderFactory
{
    public function __invoke(ContainerInterface $container): ErrorListenerProvider
    {
        $provider = new ErrorListenerProvider();

        $config = $container->get('config');
        $listeners = $config[ConfigKey::CLI_LISTENERS] ?? [];

        foreach ($listeners as $entryId) {
            $provider->addListener($container->get($entryId));
        }

        return $provider;
    }
}
