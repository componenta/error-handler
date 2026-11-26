<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\ConfigKey;
use Componenta\Error\Event\ErrorListenerProvider;
use Psr\Container\ContainerInterface;
use Componenta\Config\Config;

final readonly class CliListenerProviderFactory
{
    public function __invoke(ContainerInterface $container): ErrorListenerProvider
    {
        $provider = new ErrorListenerProvider();

        $config = $container->get(Config::class);
        $listeners = $config->array(ConfigKey::CLI_LISTENERS, []);

        foreach ($listeners as $entryId) {
            $provider->addListener($container->get($entryId));
        }

        return $provider;
    }
}
