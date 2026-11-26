<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\Event\ErrorListenerProvider;
use Componenta\Error\Reporter\ErrorReporter;
use Psr\Container\ContainerInterface;
use Componenta\Error\Internal\TypedService;
use Componenta\Error\Clock\UtcClock;
use Psr\Clock\ClockInterface;

/** Builds an isolated reporter; the unscoped reporter has no transport listeners. */
final readonly class ErrorReporterFactory
{
    public function __construct(private ?string $listenerKey = null)
    {
    }

    public function __invoke(ContainerInterface $container): ErrorReporter
    {
        $provider = $this->listenerKey !== null && $container->has($this->listenerKey)
            ? TypedService::get($container, $this->listenerKey, \Componenta\Error\Event\ErrorListenerProviderInterface::class)
            : null;

        $clock = $container->has(ClockInterface::class)
            ? TypedService::get($container, ClockInterface::class, ClockInterface::class)
            : new UtcClock();

        return new ErrorReporter($provider ?? new ErrorListenerProvider(), clock: $clock);
    }
}
