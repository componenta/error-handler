<?php

declare(strict_types=1);

namespace Componenta\Error\Event\Provider;

use Componenta\Error\Event\ErrorListenerProviderInterface;
use Componenta\Error\Internal\FailureLog;
use InvalidArgumentException;
use Throwable;
use Componenta\Error\Internal\ListenerBoundary;

/** Composes independent sources in declaration order, isolating source failures. */
final readonly class CompositeErrorListenerProvider implements ErrorListenerProviderInterface
{
    /** @var list<ErrorListenerProviderInterface> */
    private array $providers;

    /** @param iterable<mixed> $providers Error listener provider instances. */
    public function __construct(iterable $providers = [])
    {
        $snapshot = [];
        $seen = [];
        foreach ($providers as $provider) {
            if (!$provider instanceof ErrorListenerProviderInterface || $provider === $this) {
                throw new InvalidArgumentException('Invalid error listener provider.');
            }
            if (!isset($seen[spl_object_id($provider)])) {
                $seen[spl_object_id($provider)] = $provider;
                $snapshot[] = $provider;
            }
        }
        $this->providers = $snapshot;
    }

    public function provideFor(Throwable $exception): iterable
    {
        $seen = [];
        foreach ($this->providers as $provider) {
            try {
                foreach ($provider->provideFor($exception) as $listener) {
                    try {
                        $listener = ListenerBoundary::requireListener($listener);
                    } catch (Throwable $failure) {
                        // A malformed entry is recoverable while the iterable itself remains usable.
                        FailureLog::write('error listener provider entry', $failure, $exception);
                        continue;
                    }
                    if (!isset($seen[spl_object_id($listener)])) {
                        $seen[spl_object_id($listener)] = $listener;
                        yield $listener;
                    }
                }
            } catch (Throwable $failure) {
                FailureLog::write('error listener provider', $failure, $exception);
            }
        }
    }
}
