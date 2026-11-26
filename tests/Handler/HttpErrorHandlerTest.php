<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Handler;

use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Context\CliContext;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\ErrorListenerProvider;
use Componenta\Error\Handler\HttpErrorHandler;
use Componenta\Error\Reporter\ErrorReporter;
use Componenta\Error\Reporter\Policy\NonReportableErrors;
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

        self::assertSame(400, $handler->handle(new ErrorOccurrence(new InvalidArgumentException(), 'ERR-test'), $context)->getStatusCode());
        self::assertSame(503, $handler->handle(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), $context)->getStatusCode());
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
            new ErrorOccurrence(new RuntimeException(), 'ERR-test'),
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
            new ErrorOccurrence(new RuntimeException(), 'ERR-test'),
            new HttpContext(new ServerRequest('GET', '/')),
        );

        self::assertSame(201, $response->getStatusCode());
    }

    public function testHandleReportsExceptionWithResponseStatusAndErrorId(): void
    {
        $factory = new Psr17Factory();
        $reported = [];
        $listeners = new ErrorListenerProvider([ErrorListener::createFrom(
            static function (ErrorEventInterface $event) use (&$reported): void {
                $reported[] = $event;
            },
        )]);

        $handler = new HttpErrorHandler(
            new HttpErrorHandlerStub($factory->createResponse(500)),
            new ErrorReporter($listeners),
        );

        $response = $handler->handle(
            new ErrorOccurrence($exception = new RuntimeException(), 'ERR-test'),
            new HttpContext(new ServerRequest('GET', '/')),
        );

        self::assertCount(1, $reported);
        self::assertSame($exception, $reported[0]->error->exception);
        self::assertSame($response->getStatusCode(), $reported[0]->context->getAttribute(\Componenta\Error\Context\ErrorContextAttribute::HTTP_STATUS_CODE));
        self::assertNotSame('', $reported[0]->error->id);
    }

    public function testReportSkipsWhenStringFilterMatches(): void
    {
        $reported = [];
        $handler = $this->handlerWithReporter($reported, new NonReportableErrors([RuntimeException::class]));

        $handler->handle(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), $this->context());
        $handler->handle(new ErrorOccurrence($shouldReport = new InvalidArgumentException(), 'ERR-test'), $this->context());

        self::assertSame([$shouldReport], $reported);
    }

    public function testReportSkipsWhenArrayFilterMatches(): void
    {
        $reported = [];
        $handler = $this->handlerWithReporter($reported, new NonReportableErrors([[RuntimeException::class, InvalidArgumentException::class]]));

        $handler->handle(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), $this->context());
        $handler->handle(new ErrorOccurrence(new InvalidArgumentException(), 'ERR-test'), $this->context());
        $handler->handle(new ErrorOccurrence($shouldReport = new LogicException(), 'ERR-test'), $this->context());

        self::assertSame([$shouldReport], $reported);
    }

    public function testReportSkipsWhenCallableFilterReturnsTrue(): void
    {
        $reported = [];
        $handler = $this->handlerWithReporter($reported, new NonReportableErrors([static fn(ErrorOccurrence $e, ErrorContextInterface $c): bool => $e->exception->getCode() < 500]));

        $handler->handle(new ErrorOccurrence(new RuntimeException('client error', 400), 'ERR-test'), $this->context());
        $handler->handle(new ErrorOccurrence(new RuntimeException('not found', 404), 'ERR-test'), $this->context());
        $handler->handle(new ErrorOccurrence($shouldReport = new RuntimeException('server error', 500), 'ERR-test'), $this->context());

        self::assertSame([$shouldReport], $reported);
    }

    public function testSupportsOnlyHttpContext(): void
    {
        $factory = new Psr17Factory();
        $handler = new HttpErrorHandler(
            new HttpErrorHandlerStub($factory->createResponse(500)),
        );

        self::assertTrue($handler->supports(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), $this->context()));
        self::assertFalse($handler->supports(
            new ErrorOccurrence(new RuntimeException(), 'ERR-test'),
            new CliContext(new ArrayInput([]), new BufferedOutput()),
        ));
    }

    /**
     * @param list<Throwable> $reported
     */
    private function handlerWithReporter(array &$reported, NonReportableErrors $policy): HttpErrorHandler
    {
        $factory = new Psr17Factory();
        $listeners = new ErrorListenerProvider([ErrorListener::createFrom(
            static function (ErrorEventInterface $event) use (&$reported): void {
                $reported[] = $event->error->exception;
            },
        )]);

        $reporter = new ErrorReporter($listeners, $policy);
        return new HttpErrorHandler(
            new HttpErrorHandlerStub($factory->createResponse(500)),
            $reporter,
        );
    }

    private function context(): HttpContext
    {
        return new HttpContext(new ServerRequest('GET', '/'));
    }
}
