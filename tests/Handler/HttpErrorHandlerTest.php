<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Handler;

use Componenta\Error\Context\CliContext;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\ErrorListenerProvider;
use Componenta\Error\Handler\HttpErrorHandler;
use Componenta\Error\Reporter\ErrorReporter;
use Componenta\Error\Tests\Fixture\HttpErrorHandlerStub;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

#[TestDox('HttpErrorHandler')]
final class HttpErrorHandlerTest extends TestCase
{
    public function testHandleReturnsResponseFromGeneratorThatSupportsException(): void
    {
        $factory = new Psr17Factory();
        $handler = new HttpErrorHandler(
            new HttpErrorHandlerStub($factory->createResponse(500)),
        );
        $handler->addGenerator(new HttpErrorHandlerStub(
            $factory->createResponse(400),
            supportsException: InvalidArgumentException::class,
        ));
        $handler->addGenerator(new HttpErrorHandlerStub(
            $factory->createResponse(503),
            supportsException: RuntimeException::class,
        ));

        $context = new HttpContext(new ServerRequest('GET', '/'));

        self::assertSame(400, $handler->handle(new InvalidArgumentException(), $context)->getStatusCode());
        self::assertSame(503, $handler->handle(new RuntimeException(), $context)->getStatusCode());
    }

    public function testHandleReturnsResponseFromFallbackWhenNoGeneratorSupports(): void
    {
        $factory = new Psr17Factory();
        $handler = new HttpErrorHandler(
            new HttpErrorHandlerStub($factory->createResponse(500)),
        );
        $handler->addGenerator(new HttpErrorHandlerStub(
            $factory->createResponse(201),
            supports: false,
        ));

        $response = $handler->handle(
            new RuntimeException(),
            new HttpContext(new ServerRequest('GET', '/')),
        );

        self::assertSame(500, $response->getStatusCode());
    }

    public function testHandleReturnsResponseFromHigherPriorityGenerator(): void
    {
        $factory = new Psr17Factory();
        $handler = new HttpErrorHandler(
            new HttpErrorHandlerStub($factory->createResponse(500)),
        );
        $handler->addGenerator(new HttpErrorHandlerStub($factory->createResponse(200)));
        $handler->addGenerator(new HttpErrorHandlerStub($factory->createResponse(201)), priority: 100);

        $response = $handler->handle(
            new RuntimeException(),
            new HttpContext(new ServerRequest('GET', '/')),
        );

        self::assertSame(201, $response->getStatusCode());
    }

    public function testHandleReportsExceptionWithResponseAndErrorId(): void
    {
        $factory = new Psr17Factory();
        $reported = [];
        $listeners = new ErrorListenerProvider();
        $listeners->addListener(ErrorListener::createFrom(
            static function (ErrorEventInterface $event) use (&$reported): void {
                $reported[] = $event;
            },
        ));

        $handler = new HttpErrorHandler(
            new HttpErrorHandlerStub($factory->createResponse(500)),
            new ErrorReporter($listeners),
        );

        $response = $handler->handle(
            $exception = new RuntimeException(),
            new HttpContext(new ServerRequest('GET', '/')),
        );

        self::assertCount(1, $reported);
        self::assertSame($exception, $reported[0]->exception);
        self::assertSame($response, $reported[0]->result);
        self::assertNotSame('', $reported[0]->errorId);
    }

    public function testReportSkipsWhenStringFilterMatches(): void
    {
        $reported = [];
        $handler = $this->handlerWithReporter($reported);
        $handler->addNonReportableError(RuntimeException::class);

        $handler->handle(new RuntimeException(), $this->context());
        $handler->handle($shouldReport = new InvalidArgumentException(), $this->context());

        self::assertSame([$shouldReport], $reported);
    }

    public function testReportSkipsWhenArrayFilterMatches(): void
    {
        $reported = [];
        $handler = $this->handlerWithReporter($reported);
        $handler->addNonReportableError([RuntimeException::class, InvalidArgumentException::class]);

        $handler->handle(new RuntimeException(), $this->context());
        $handler->handle(new InvalidArgumentException(), $this->context());
        $handler->handle($shouldReport = new LogicException(), $this->context());

        self::assertSame([$shouldReport], $reported);
    }

    public function testReportSkipsWhenCallableFilterReturnsTrue(): void
    {
        $reported = [];
        $handler = $this->handlerWithReporter($reported);
        $handler->addNonReportableError(
            static fn(Throwable $e, ErrorContextInterface $c): bool => $e->getCode() < 500,
        );

        $handler->handle(new RuntimeException('client error', 400), $this->context());
        $handler->handle(new RuntimeException('not found', 404), $this->context());
        $handler->handle($shouldReport = new RuntimeException('server error', 500), $this->context());

        self::assertSame([$shouldReport], $reported);
    }

    public function testSupportsOnlyHttpContext(): void
    {
        $factory = new Psr17Factory();
        $handler = new HttpErrorHandler(
            new HttpErrorHandlerStub($factory->createResponse(500)),
        );

        self::assertTrue($handler->supports(new RuntimeException(), $this->context()));
        self::assertFalse($handler->supports(
            new RuntimeException(),
            new CliContext(new ArrayInput([]), new BufferedOutput()),
        ));
    }

    /**
     * @param list<Throwable> $reported
     */
    private function handlerWithReporter(array &$reported): HttpErrorHandler
    {
        $factory = new Psr17Factory();
        $listeners = new ErrorListenerProvider();
        $listeners->addListener(ErrorListener::createFrom(
            static function (ErrorEventInterface $event) use (&$reported): void {
                $reported[] = $event->exception;
            },
        ));

        return new HttpErrorHandler(
            new HttpErrorHandlerStub($factory->createResponse(500)),
            new ErrorReporter($listeners),
        );
    }

    private function context(): HttpContext
    {
        return new HttpContext(new ServerRequest('GET', '/'));
    }
}
