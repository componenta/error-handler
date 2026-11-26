<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\ConfigKey;
use Componenta\Error\Event\ErrorListenerProvider;
use Psr\Container\ContainerInterface;
use Componenta\Error\Internal\TypedService;
use Componenta\Config\Config;

final readonly class CliListenerProviderFactory
{
    public function __invoke(ContainerInterface $container): ErrorListenerProvider
    {
        $provider = new ErrorListenerProvider();

        $config = TypedService::get($container, Config::class, Config::class);
        $listeners = TypedService::ids($config->array(ConfigKey::CLI_LISTENERS, []), ConfigKey::CLI_LISTENERS);

        foreach ($listeners as $entryId) {
            $provider->addListener(TypedService::get($container, $entryId, \Componenta\Error\Event\ErrorListenerInterface::class));
        }

        return $provider;
    }
}
