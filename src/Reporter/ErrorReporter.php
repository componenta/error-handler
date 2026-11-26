<?php

declare(strict_types=1);

namespace Componenta\Error\Reporter;

use Componenta\Error\Clock\UtcClock;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Event\ErrorEvent;
use Componenta\Error\Event\Provider\ArrayErrorListenerProvider;
use Componenta\Error\Event\ErrorListenerInterface;
use UnexpectedValueException;
use Componenta\Error\Event\ErrorListenerProviderInterface;
use Componenta\Error\Internal\FailureLog;
use Componenta\Error\Reporter\Policy\ErrorReportingPolicyInterface;
use Componenta\Error\Reporter\Policy\NonReportableErrors;
use DateTimeImmutable;
use Fiber;
use Psr\Clock\ClockInterface;
use Throwable;
use WeakMap;

final class ErrorReporter implements ErrorReporterInterface
{
    private bool $reportingOnMain = false;

    /** @var WeakMap<object, true>|null */
    private ?WeakMap $reportingFibers = null;

    public function __construct(
        private readonly ErrorListenerProviderInterface $listeners = new ArrayErrorListenerProvider(),
        private readonly ErrorReportingPolicyInterface $policy = new NonReportableErrors(),
        private readonly ClockInterface $clock = new UtcClock(),
        private readonly ListenerExceptionPolicy $listenerExceptionPolicy = ListenerExceptionPolicy::Swallow,
    ) {
    }

    public function report(ErrorOccurrence $error, ErrorContextInterface $context): void
    {
        $fiber = Fiber::getCurrent();
        if (!$this->enter($fiber)) {
            return;
        }

        try {
            try {
                if (!$this->policy->shouldReport($error, $context)) {
                    return;
                }
            } catch (Throwable $failure) {
                // A broken exclusion rule must not silently discard the error.
                FailureLog::write('reporting policy', $failure, $error->exception);
            }

            $event = null;
            $seen = [];

            foreach ($this->listeners->provideFor($error->exception) as $listener) {
                try {
                    if (!$listener instanceof ErrorListenerInterface) {
                        throw new UnexpectedValueException('Provider must yield error listeners.');
                    }
                    if (isset($seen[spl_object_id($listener)])) {
                        continue;
                    }
                    $seen[spl_object_id($listener)] = $listener;
                    if ($listener->supports($error, $context)) {
                        $event ??= new ErrorEvent($error, $context, $this->timestamp($error));
                        $listener->handleEvent($event);
                    }
                } catch (Throwable $failure) {
                    if ($this->listenerExceptionPolicy === ListenerExceptionPolicy::Throw) {
                        throw $failure;
                    }
                    FailureLog::write('error listener', $failure, $error->exception);
                }
            }
        } catch (Throwable $failure) {
            if ($this->listenerExceptionPolicy === ListenerExceptionPolicy::Throw) {
                throw $failure;
            }
            FailureLog::write('reporting', $failure, $error->exception);
        } finally {
            $this->leave($fiber);
        }
    }

    private function timestamp(ErrorOccurrence $error): DateTimeImmutable
    {
        try {
            return $this->clock->now();
        } catch (Throwable $failure) {
            FailureLog::write('reporting clock', $failure, $error->exception);

            return (new UtcClock())->now();
        }
    }

    private function enter(?object $fiber): bool
    {
        if ($fiber === null) {
            if ($this->reportingOnMain) {
                return false;
            }
            $this->reportingOnMain = true;
            return true;
        }

        $active = $this->reportingFibers ??= new WeakMap();
        if (isset($active[$fiber])) {
            return false;
        }
        $active[$fiber] = true;

        return true;
    }

    private function leave(?object $fiber): void
    {
        if ($fiber === null) {
            $this->reportingOnMain = false;
        } elseif ($this->reportingFibers !== null) {
            unset($this->reportingFibers[$fiber]);
        }
    }
}
