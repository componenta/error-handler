<?php

declare(strict_types=1);

namespace Componenta\Error\Event\Provider;

use Componenta\Error\Event\ErrorListenerInterface;
use Componenta\Error\Event\ErrorListenerProviderInterface;
use Componenta\Error\Internal\FailureLog;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;

/** Composes independent sources in declaration order, isolating source failures. */
final readonly class CompositeErrorListenerProvider implements ErrorListenerProviderInterface
{
    /** @var list<ErrorListenerProviderInterface> */
    private array $providers;

    /** @param iterable<ErrorListenerProviderInterface> $providers */
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
                    if (!$listener instanceof ErrorListenerInterface) {
                        throw new UnexpectedValueException('Provider must yield error listeners.');
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
