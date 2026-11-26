<?php

declare(strict_types=1);

namespace Componenta\Error\Http\Middleware;

use Throwable;
use Componenta\Error\Http\EmergencyHttpResponseGenerator;
use Componenta\Error\ErrorOccurrenceFactory;
use Componenta\Error\Internal\FailureLog;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\Handler\HttpErrorHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware for HTTP error handling
 *
 * Converts PHP errors to exceptions and catches all exceptions during request
 * processing. Reports exceptions to error handler and returns error response.
 * Automatically restores error handler state after processing.
 */
final readonly class ErrorHandlerMiddleware implements MiddlewareInterface
{

    /**
     * @param HttpErrorHandlerInterface $errorHandler Handler for caught exceptions
     * @param int $errorLevel Error conversion mask, intersected with process error_reporting() (default: E_ALL)
     */
    public function __construct(
        private HttpErrorHandlerInterface $errorHandler,
        private int $errorLevel = E_ALL,
        private ?EmergencyHttpResponseGenerator $emergencyGenerator = null,
        private ErrorOccurrenceFactory $errorFactory = new ErrorOccurrenceFactory(),
    ) {
    }

    /**
     * Process HTTP request with error handling
     *
     * Sets up custom error handler that converts errors to ErrorException,
     * catches all exceptions, reports them, and returns error response.
     * Ensures error handler is always restored via finally block.
     *
     * @param ServerRequestInterface $request Incoming HTTP request
     * @param RequestHandlerInterface $handler Next handler in chain
     * @return ResponseInterface HTTP response (normal or error response)
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $oldLevel = error_reporting();
        set_error_handler(static function (int $errno, string $msg, string $file, int $line): bool {
            if ((error_reporting() & $errno) === 0) {
                return false;
            }

            throw new \ErrorException($msg, 0, $errno, $file, $line);
        }, $this->errorLevel);

        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            $context = new HttpContext($request);
            $error = null;
            try {
                $error = $this->errorFactory->create($e);
                return $this->errorHandler->handle($error, $context);
            } catch (Throwable $failure) {
                FailureLog::write('middleware error handler', $failure, $e);
                if ($this->emergencyGenerator !== null) {
                    try {
                        return $error === null
                            ? $this->emergencyGenerator->generateUncorrelated($context)
                            : $this->emergencyGenerator->generate($error, $context);
                    } catch (Throwable $secondary) {
                        FailureLog::write('middleware emergency response', $secondary, $e);
                    }
                }
                throw $e;
            }
        } finally {
            restore_error_handler();
            error_reporting($oldLevel);
        }
    }
}
