<?php

declare(strict_types=1);

namespace Componenta\Error\Listener;

use Componenta\Error\Context\CliErrorContextInterface;
use Componenta\Error\Context\ErrorContextAttribute;
use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListenerInterface;
use Componenta\Error\Internal\FailureLog;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Forwards reported errors to an application-supplied PSR-3 logger.
 *
 * By default only correlation and bounded structural metadata are supplied.
 * Enabling exception forwarding grants the logger access to the original
 * Throwable, including potentially confidential messages, traces and arguments.
 */
final readonly class Psr3ErrorListener implements ErrorListenerInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private bool $includeException = false,
    ) {
    }

    public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool
    {
        return true;
    }

    public function handleEvent(ErrorEventInterface $event): void
    {
        $error = $event->error;
        $exception = $error->exception;
        $context = $event->context;

        $record = [
            'error_id' => $error->id,
            'exception_type' => get_debug_type($exception),
            'timestamp' => $event->timestamp->format('Y-m-d\TH:i:s.uP'),
        ];

        if ($context instanceof HttpErrorContextInterface) {
            $record['transport'] = 'http';

            // The status is optional metadata from the actual response.
            // A failing context accessor must not discard the primary error.
            try {
                $status = $context->getAttribute(ErrorContextAttribute::HTTP_STATUS_CODE, null);
                if (is_int($status) && $status >= 100 && $status <= 599) {
                    $record['status'] = $status;
                }
            } catch (Throwable $failure) {
                FailureLog::write('PSR-3 error metadata', $failure, $exception);
            }
        } elseif ($context instanceof CliErrorContextInterface) {
            $record['transport'] = 'cli';
        }

        if ($this->includeException) {
            $record['exception'] = $exception;
        }

        // The reporter owns failure handling, filtering and recursion protection.
        $this->logger->log(
            $exception instanceof \Error ? LogLevel::CRITICAL : LogLevel::ERROR,
            'Application error {error_id} ({exception_type})',
            $record,
        );
    }
}
