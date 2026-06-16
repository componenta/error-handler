<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Handler;

use Componenta\Error\Context\CliContext;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\ErrorListenerProvider;
use Componenta\Error\Handler\CliErrorHandler;
use Componenta\Error\Reporter\ErrorReporter;
use Componenta\Error\Tests\Fixture\CliErrorHandlerStub;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

#[TestDox('CliErrorHandler')]
final class CliErrorHandlerTest extends TestCase
{
    public function testHandleWritesToOutputFromHandlerThatSupportsException(): void
    {
        $handler = new CliErrorHandler(new CliErrorHandlerStub(renderOutput: 'fallback'));
        $handler->addHandler(new CliErrorHandlerStub(
            supportsException: InvalidArgumentException::class,
            renderOutput: 'invalid argument',
        ));
        $handler->addHandler(new CliErrorHandlerStub(
            supportsException: RuntimeException::class,
            renderOutput: 'runtime',
        ));

        $output1 = new BufferedOutput();
        $handler->handle(new InvalidArgumentException(), new CliContext(new ArrayInput([]), $output1));

        $output2 = new BufferedOutput();
        $handler->handle(new RuntimeException(), new CliContext(new ArrayInput([]), $output2));

        self::assertStringContainsString('invalid argument', $output1->fetch());
        self::assertStringContainsString('runtime', $output2->fetch());
    }

    public function testHandleWritesToOutputFromFallbackWhenNoHandlerSupports(): void
    {
        $handler = new CliErrorHandler(new CliErrorHandlerStub(renderOutput: 'fallback'));
        $handler->addHandler(new CliErrorHandlerStub(
            supportsException: InvalidArgumentException::class,
            renderOutput: 'not used',
        ));

        $output = new BufferedOutput();
        $handler->handle(new RuntimeException(), new CliContext(new ArrayInput([]), $output));

        self::assertStringContainsString('fallback', $output->fetch());
    }

    public function testHandleWritesToOutputFromHigherPriorityHandler(): void
    {
        $handler = new CliErrorHandler(new CliErrorHandlerStub(renderOutput: 'fallback'));
        $handler->addHandler(new CliErrorHandlerStub(renderOutput: 'low'));
        $handler->addHandler(new CliErrorHandlerStub(renderOutput: 'high'), priority: 100);

        $output = new BufferedOutput();
        $handler->handle(new RuntimeException(), new CliContext(new ArrayInput([]), $output));

        self::assertStringContainsString('high', $output->fetch());
    }

    public function testHandleReportsExceptionWithErrorId(): void
    {
        $reported = [];
        $listeners = new ErrorListenerProvider();
        $listeners->addListener(ErrorListener::createFrom(
            static function (ErrorEventInterface $event) use (&$reported): void {
                $reported[] = $event;
            },
        ));

        $handler = new CliErrorHandler(
            new CliErrorHandlerStub(renderOutput: 'error'),
            new ErrorReporter($listeners),
        );

        $handler->handle(
            $exception = new RuntimeException(),
            new CliContext(new ArrayInput([]), new BufferedOutput()),
        );

        self::assertCount(1, $reported);
        self::assertSame($exception, $reported[0]->exception);
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

    public function testSupportsOnlyCliContext(): void
    {
        $handler = new CliErrorHandler(new CliErrorHandlerStub());

        self::assertTrue($handler->supports(new RuntimeException(), $this->context()));
        self::assertFalse($handler->supports(
            new RuntimeException(),
            new HttpContext(new ServerRequest('GET', '/')),
        ));
    }

    /**
     * @param list<Throwable> $reported
     */
    private function handlerWithReporter(array &$reported): CliErrorHandler
    {
        $listeners = new ErrorListenerProvider();
        $listeners->addListener(ErrorListener::createFrom(
            static function (ErrorEventInterface $event) use (&$reported): void {
                $reported[] = $event->exception;
            },
        ));

        return new CliErrorHandler(
            new CliErrorHandlerStub(renderOutput: 'error'),
            new ErrorReporter($listeners),
        );
    }

    private function context(): CliContext
    {
        return new CliContext(new ArrayInput([]), new BufferedOutput());
    }
}
