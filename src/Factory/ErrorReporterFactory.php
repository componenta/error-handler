<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\Event\ErrorListenerProvider;
use Componenta\Error\Reporter\ErrorReporter;
use Psr\Container\ContainerInterface;

/** Builds an isolated reporter; the unscoped reporter has no transport listeners. */
final readonly class ErrorReporterFactory
{
    public function __construct(private ?string $listenerKey = null)
    {
    }

    public function __invoke(ContainerInterface $container): ErrorReporter
    {
        $provider = $this->listenerKey !== null && $container->has($this->listenerKey)
            ? $container->get($this->listenerKey)
            : null;

        return new ErrorReporter(ErrorListenerProvider::createFrom($provider));
    }
}
