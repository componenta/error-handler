<?php

declare(strict_types=1);

namespace Componenta\Error\Http\Middleware;

use Throwable;
use Componenta\Error\ConfigKey;
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
    public const string ERROR_LEVEL = ConfigKey::ERROR_LEVEL;

    /**
     * @param HttpErrorHandlerInterface $errorHandler Handler for caught exceptions
     * @param int $errorLevel Error reporting level (default: E_ALL)
     */
    public function __construct(
        private HttpErrorHandlerInterface $errorHandler,
        private int $errorLevel = E_ALL,
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
        $oldLevel = error_reporting($this->errorLevel);
        set_error_handler(function (int $errno, string $msg, string $file, int $line): void {
            if ((error_reporting() & $errno) === 0) {
                return;
            }

            throw new \ErrorException($msg, 0, $errno, $file, $line);
        }, $this->errorLevel);

        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            return $this->errorHandler->handle($e, new HttpContext($request));
        } finally {
            restore_error_handler();
            error_reporting($oldLevel);
        }
    }
}
