<?php

declare(strict_types=1);

namespace Componenta\Error\Factory;

use Componenta\Error\Clock\UtcClock;
use Componenta\Error\Event\ErrorListenerProvider;
use Componenta\Error\Event\ErrorListenerProviderInterface;
use Componenta\Error\Internal\TypedService;
use Componenta\Error\Reporter\ErrorReporter;
use Componenta\Error\Reporter\Policy\ErrorReportingPolicyInterface;
use Componenta\Error\Reporter\Policy\NonReportableErrors;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

final readonly class ErrorReporterFactory
{
    public function __invoke(ContainerInterface $container): ErrorReporter
    {
        return new ErrorReporter(
            listeners: $container->has(ErrorListenerProviderInterface::class)
                ? TypedService::get($container, ErrorListenerProviderInterface::class, ErrorListenerProviderInterface::class)
                : new ErrorListenerProvider(),
            policy: $container->has(ErrorReportingPolicyInterface::class)
                ? TypedService::get($container, ErrorReportingPolicyInterface::class, ErrorReportingPolicyInterface::class)
                : new NonReportableErrors(),
            clock: $container->has(ClockInterface::class)
                ? TypedService::get($container, ClockInterface::class, ClockInterface::class)
                : new UtcClock(),
        );
    }
}
