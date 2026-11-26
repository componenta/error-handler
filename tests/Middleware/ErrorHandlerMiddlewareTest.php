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

        $previous = error_reporting(E_ALL);
        try {
            $response = $middleware->process(new ServerRequest('GET', '/'), $handler);
            self::assertSame(500, $response->getStatusCode());
            self::assertSame('Test PHP error', $errorHandler->handledExceptions[0]->getMessage());
        } finally {
            error_reporting($previous);
        }
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

    public function testMiddlewarePreservesOneOccurrenceAcrossRenderingAndReporting(): void
    {
        $exception = new RuntimeException('original');
        $generator = $this->createMock(\Componenta\Error\ErrorId\ErrorIdGeneratorInterface::class);
        $generator->expects(self::once())->method('generate')->willReturn('ERR-correlation');
        $errorFactory = new \Componenta\Error\ErrorOccurrenceFactory($generator);
        $rendered = [];
        $reported = [];
        $renderer = $this->createMock(\Componenta\Error\Renderer\ErrorRendererInterface::class);
        $renderer->method('supports')->willReturn(true);
        $renderer->expects(self::once())->method('render')->willReturnCallback(
            static function (\Componenta\Error\ErrorOccurrence $error, \Componenta\Error\ErrorContextInterface $context) use (&$rendered): \Componenta\Error\Renderer\RenderedError {
                $rendered[] = $error;
                return new \Componenta\Error\Renderer\RenderedError($error->id, 'text/plain');
            },
        );
        $reporter = new \Componenta\Error\Reporter\ErrorReporter(
            new \Componenta\Error\Event\Provider\ArrayErrorListenerProvider([
                \Throwable::class => [\Componenta\Error\Event\ErrorListener::createFrom(
                    static function (\Componenta\Error\Event\ErrorEventInterface $event) use (&$reported): void { $reported[] = $event->error; },
                )],
            ]),
        );
        $middleware = new ErrorHandlerMiddleware(
            HttpErrorHandler::createDefaults(new Psr17Factory(), $renderer, $reporter),
            errorFactory: $errorFactory,
        );
        $next = $this->createStub(RequestHandlerInterface::class);
        $next->method('handle')->willThrowException($exception);
        $response = $middleware->process(new ServerRequest('GET', '/'), $next);

        self::assertCount(1, $rendered);
        self::assertCount(1, $reported);
        self::assertSame($exception, $rendered[0]->exception);
        self::assertSame($rendered[0], $reported[0]);
        self::assertSame('ERR-correlation', $response->getHeaderLine('X-Error-ID'));
        self::assertSame('ERR-correlation', (string) $response->getBody());
    }

    public function testMiddlewareEmergencyPathReusesOriginalCorrelationWithoutCreatingAnotherOccurrence(): void
    {
        $exception = new RuntimeException('private primary failure');
        $generator = $this->createMock(\Componenta\Error\ErrorId\ErrorIdGeneratorInterface::class);
        $generator->expects(self::once())->method('generate')->willReturn('ERR-emergency');
        $handler = $this->createMock(\Componenta\Error\Handler\HttpErrorHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturnCallback(
            static function (\Componenta\Error\ErrorOccurrence $error, \Componenta\Error\Context\HttpErrorContextInterface $context) use ($exception): never {
                self::assertSame($exception, $error->exception);
                self::assertSame('ERR-emergency', $error->id);
                throw new RuntimeException('private secondary failure');
            },
        );
        $middleware = new ErrorHandlerMiddleware(
            $handler,
            emergencyGenerator: new \Componenta\Error\Http\EmergencyHttpResponseGenerator(new Psr17Factory()),
            errorFactory: new \Componenta\Error\ErrorOccurrenceFactory($generator),
        );
        $next = $this->createStub(RequestHandlerInterface::class);
        $next->method('handle')->willThrowException($exception);
        $response = $middleware->process(new ServerRequest('GET', '/'), $next);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('ERR-emergency', $response->getHeaderLine('X-Error-ID'));
        self::assertSame("An unexpected error occurred\nError ID: ERR-emergency", (string) $response->getBody());
    }

    public function testFailedEmergencyResponseRethrowsOriginalThrowable(): void
    {
        $original = new RuntimeException('primary');
        $handler = $this->createStub(\Componenta\Error\Handler\HttpErrorHandlerInterface::class);
        $handler->method('handle')->willThrowException(new RuntimeException('secondary'));
        $factory = $this->createStub(\Psr\Http\Message\ResponseFactoryInterface::class);
        $factory->method('createResponse')->willThrowException(new RuntimeException('emergency failure'));
        $middleware = new ErrorHandlerMiddleware($handler, emergencyGenerator: new \Componenta\Error\Http\EmergencyHttpResponseGenerator($factory));
        $next = $this->createStub(RequestHandlerInterface::class);
        $next->method('handle')->willThrowException($original);
        try {
            $middleware->process(new ServerRequest('GET', '/'), $next);
            self::fail('Original throwable must propagate if the emergency response fails.');
        } catch (RuntimeException $caught) {
            self::assertSame($original, $caught);
        }
    }

    public function testUncorrelatedEmergencyResponseNeverInventsAnIdentifier(): void
    {
        $emergency = new \Componenta\Error\Http\EmergencyHttpResponseGenerator(new Psr17Factory());
        $response = $emergency->generateUncorrelated(new \Componenta\Error\Context\HttpContext(new ServerRequest('GET', '/')));
        self::assertSame(500, $response->getStatusCode());
        self::assertFalse($response->hasHeader('X-Error-ID'));
        self::assertSame('An unexpected error occurred', (string) $response->getBody());
        $head = $emergency->generateUncorrelated(new \Componenta\Error\Context\HttpContext(new ServerRequest('HEAD', '/')));
        self::assertSame('', (string) $head->getBody());
    }
}
