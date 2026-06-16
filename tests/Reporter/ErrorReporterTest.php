<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Reporter;

use Componenta\Error\Context\CliContext;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\ErrorListenerProvider;
use Componenta\Error\Reporter\ErrorReporter;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

#[TestDox('ErrorReporter')]
final class ErrorReporterTest extends TestCase
{
    public function testReportDispatchesEventToListeners(): void
    {
        $receivedEvents = [];
        $listeners = new ErrorListenerProvider();
        $listeners->addListener(ErrorListener::createFrom(
            static function (ErrorEventInterface $event) use (&$receivedEvents): void {
                $receivedEvents[] = $event;
            },
        ));

        $reporter = new ErrorReporter($listeners);
        $exception = new RuntimeException('test');
        $context = $this->context();

        $reporter->report($exception, $context, result: 'handled', errorId: 'err-1');

        self::assertCount(1, $receivedEvents);
        self::assertSame($exception, $receivedEvents[0]->exception);
        self::assertSame($context, $receivedEvents[0]->context);
        self::assertSame('handled', $receivedEvents[0]->result);
        self::assertSame('err-1', $receivedEvents[0]->errorId);
    }

    public function testReportSkipsWhenStringFilterMatches(): void
    {
        $reported = [];
        $reporter = $this->reporter($reported);

        $reporter->addNonReportableError(RuntimeException::class);
        $reporter->report(new RuntimeException(), $this->context());
        $reporter->report($shouldReport = new InvalidArgumentException(), $this->context());

        self::assertSame([$shouldReport], $reported);
    }

    public function testReportSkipsWhenArrayFilterMatches(): void
    {
        $reported = [];
        $reporter = $this->reporter($reported);

        $reporter->addNonReportableError([RuntimeException::class, InvalidArgumentException::class]);
        $reporter->report(new RuntimeException(), $this->context());
        $reporter->report(new InvalidArgumentException(), $this->context());
        $reporter->report($shouldReport = new LogicException(), $this->context());

        self::assertSame([$shouldReport], $reported);
    }

    public function testReportSkipsWhenCallableFilterReturnsTrue(): void
    {
        $reported = [];
        $reporter = $this->reporter($reported);

        $reporter->addNonReportableError(
            static fn(Throwable $e, ErrorContextInterface $c): bool => $e->getCode() < 500,
        );
        $reporter->report(new RuntimeException('client error', 400), $this->context());
        $reporter->report(new RuntimeException('not found', 404), $this->context());
        $reporter->report($shouldReport = new RuntimeException('server error', 500), $this->context());

        self::assertSame([$shouldReport], $reported);
    }

    /**
     * @param list<Throwable> $reported
     */
    private function reporter(array &$reported): ErrorReporter
    {
        $listeners = new ErrorListenerProvider();
        $listeners->addListener(ErrorListener::createFrom(
            static function (ErrorEventInterface $event) use (&$reported): void {
                $reported[] = $event->exception;
            },
        ));

        return new ErrorReporter($listeners);
    }

    private function context(): CliContext
    {
        return new CliContext(new ArrayInput([]), new BufferedOutput());
    }
}
