<?php

declare(strict_types=1);

namespace Componenta\Error\Reporter;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Event\ErrorListenerInterface;
use Componenta\Error\Event\ErrorListenerProviderInterface;
use Componenta\Error\Reporter\ErrorReporterInterface;
use Componenta\Error\Event\ErrorEvent;
use Componenta\Error\Event\ErrorListenerProvider;
use Throwable;

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

    public function addListener(ErrorListenerInterface $listener, int $priority = 0): void
    {
        $this->listeners->addListener($listener, $priority);
    }

    /**
     * @param class-string<Throwable>|list<class-string<Throwable>>|callable(Throwable, ErrorContextInterface): bool $filter
     */
    public function addNonReportableError(string|array|callable $filter): void
    {
        $this->nonReportable[] = match (true) {
            is_string($filter) => static fn(Throwable $e, ErrorContextInterface $c): bool => $e instanceof $filter,
            is_array($filter) => static fn(Throwable $e, ErrorContextInterface $c): bool => array_any(
                $filter,
                static fn(string $class): bool => $e instanceof $class,
            ),
            default => $filter,
        };
    }

    public function report(
        Throwable $exception,
        ErrorContextInterface $context,
        mixed $result = null,
        ?string $errorId = null,
    ): void {
        if ($this->shouldNotReport($exception, $context)) {
            return;
        }

        $event = new ErrorEvent(
            exception: $exception,
            context: $context,
            result: $result,
            errorId: $errorId,
        );

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
    }

    private function shouldNotReport(Throwable $exception, ErrorContextInterface $context): bool
    {
        return array_any(
            $this->nonReportable,
            static fn(callable $filter): bool => $filter($exception, $context),
        );
    }

    private function handleListenerException(Throwable $exception): void
    {
        if ($this->listenerExceptionPolicy === ListenerExceptionPolicy::Throw) {
            throw $exception;
        }

        error_log(sprintf(
            '[error-reporter] listener failure: %s: %s in %s:%d',
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
        ));
    }
}
