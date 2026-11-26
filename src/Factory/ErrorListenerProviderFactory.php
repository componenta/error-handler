<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Config\Config;
use Componenta\Error\ConfigKey;
use Componenta\Error\Event\ErrorListenerInterface;
use Componenta\Error\Event\ErrorListenerProvider;
use Componenta\Error\Internal\TypedService;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;

final readonly class ErrorListenerProviderFactory
{
    public function __invoke(ContainerInterface $container): ErrorListenerProvider
    {
        $config = TypedService::get($container, Config::class, Config::class);
        $entries = [];

        foreach ($config->array(ConfigKey::ERROR_LISTENERS, []) as $entry) {
            if (is_string($entry) && $entry !== '') {
                $entries[] = TypedService::get($container, $entry, ErrorListenerInterface::class);
            } elseif (is_array($entry) && isset($entry['listener'], $entry['priority'])
                && is_string($entry['listener']) && $entry['listener'] !== '' && is_int($entry['priority'])) {
                $entries[] = [
                    'listener' => TypedService::get($container, $entry['listener'], ErrorListenerInterface::class),
                    'priority' => $entry['priority'],
                ];
            } else {
                throw new InvalidArgumentException('Invalid error listener configuration.');
            }
        }

        return new ErrorListenerProvider($entries);
    }
}
