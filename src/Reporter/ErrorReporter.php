<?php

declare(strict_types=1);

namespace Componenta\Error\Reporter;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Event\ErrorListenerProviderInterface;
use Componenta\Error\Reporter\ErrorReporterInterface;
use Componenta\Error\Event\ErrorEvent;
use Componenta\Error\Event\ErrorListenerProvider;
use Throwable;
use Componenta\Error\Internal\FailureLog;
use Componenta\Error\Internal\ErrorPredicate;

final class ErrorReporter implements ErrorReporterInterface
{
    /**
     * @var list<callable(Throwable, ErrorContextInterface): bool>
     */
    private array $nonReportable = [];

    public function __construct(
        private readonly ErrorListenerProviderInterface $listeners = new ErrorListenerProvider(),
        private readonly ListenerExceptionPolicy $listenerExceptionPolicy = ListenerExceptionPolicy::Swallow,
    ) {
    }

    /**
     * @param class-string<Throwable>|list<class-string<Throwable>>|callable(Throwable, ErrorContextInterface): bool $filter
     */
    public function addNonReportableError(string|array|callable $filter): void
    {
        $this->nonReportable[] = ErrorPredicate::normalize($filter);
    }

    public function report(
        Throwable $exception,
        ErrorContextInterface $context,
        mixed $result = null,
        ?string $errorId = null,
    ): void {
        try {
            $event = new ErrorEvent(
                exception: $exception,
                context: $context,
                result: $result,
                errorId: $errorId,
            );
            $context = $event->context;
            if ($this->shouldNotReport($exception, $context)) {
                return;
            }

            foreach ($this->listeners->getListeners() as $listener) {
                try {
                    if (!$listener->supports($exception, $context)) {
                        continue;
                    }

                    $listener->handleEvent($event);
                } catch (Throwable $listenerException) {
                    $this->handleListenerException($listenerException);
                }
            }
        } catch (Throwable $providerFailure) {
            $this->handleListenerException($providerFailure);
        }
    }

    private function shouldNotReport(Throwable $exception, ErrorContextInterface $context): bool
    {
        foreach ($this->nonReportable as $filter) {
            try {
                if ($filter($exception, $context)) {
                    return true;
                }
            } catch (Throwable $failure) {
                $this->handleListenerException($failure);
            }
        }
        return false;
    }

    private function handleListenerException(Throwable $exception): void
    {
        if ($this->listenerExceptionPolicy === ListenerExceptionPolicy::Throw) {
            throw $exception;
        }

        FailureLog::write('listener or filter', $exception);
    }
}
