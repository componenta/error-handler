<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Middleware;

use Componenta\Error\Http\Middleware\ErrorHandlerMiddleware;
use Componenta\Error\Handler\HttpErrorHandler;
use Componenta\Error\Tests\Fixture\HttpErrorHandlerStub;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

#[TestDox('ErrorHandlerMiddleware')]
final class ErrorHandlerMiddlewareTest extends TestCase
{
    public function testProcessReturnsResponseFromErrorHandlerWhenExceptionThrown(): void
    {
        $factory = new Psr17Factory();

        $middleware = new ErrorHandlerMiddleware(new HttpErrorHandler(
            new HttpErrorHandlerStub($factory->createResponse(500)),
        ));

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('Test error');
            }
        };

        $response = $middleware->process(new ServerRequest('GET', '/'), $handler);

        self::assertSame(500, $response->getStatusCode());
    }

    public function testProcessConvertsPhpErrorToException(): void
    {
        $factory = new Psr17Factory();

        $errorHandler = new HttpErrorHandlerStub(
            $factory->createResponse(500),
            supportsException: \ErrorException::class,
        );

        $middleware = new ErrorHandlerMiddleware(new HttpErrorHandler($errorHandler));

        $handler = new readonly class($factory) implements RequestHandlerInterface {
            public function __construct(private Psr17Factory $factory) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                trigger_error('Test PHP error', E_USER_WARNING);
                return $this->factory->createResponse(200);
            }
        };

        $response = $middleware->process(new ServerRequest('GET', '/'), $handler);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Test PHP error', $errorHandler->handledExceptions[0]->getMessage());
    }

    public function testProcessDelegatesThrowableToErrorHandler(): void
    {
        $factory = new Psr17Factory();
        $generator = new HttpErrorHandlerStub($factory->createResponse(500));

        $middleware = new ErrorHandlerMiddleware(new HttpErrorHandler($generator));

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('Test error');
            }
        };

        $middleware->process(new ServerRequest('GET', '/'), $handler);

        self::assertInstanceOf(RuntimeException::class, $generator->handledExceptions[0]);
    }
}
