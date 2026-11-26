<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Reporter;

use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Context\CliContext;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\Provider\ArrayErrorListenerProvider;
use Componenta\Error\Reporter\ErrorReporter;
use Componenta\Error\Reporter\Policy\NonReportableErrors;
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
        $listeners = new ArrayErrorListenerProvider([\Throwable::class => [ErrorListener::createFrom(
            static function (ErrorEventInterface $event) use (&$receivedEvents): void {
                $receivedEvents[] = $event;
            },
        )]]);

        $reporter = new ErrorReporter($listeners);
        $exception = new RuntimeException('test');
        $context = $this->context();

        $occurrence = new ErrorOccurrence($exception, 'err-1');
        $reporter->report($occurrence, $context);

        self::assertCount(1, $receivedEvents);
        self::assertSame($occurrence, $receivedEvents[0]->error);
        self::assertSame($context, $receivedEvents[0]->context);
        self::assertSame('err-1', $receivedEvents[0]->error->id);
    }

    public function testReportSkipsWhenStringFilterMatches(): void
    {
        $reported = [];
        $reporter = $this->reporter($reported, new NonReportableErrors([RuntimeException::class]));
        $reporter->report(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), $this->context());
        $reporter->report(new ErrorOccurrence($shouldReport = new InvalidArgumentException(), 'ERR-test'), $this->context());

        self::assertSame([$shouldReport], $reported);
    }

    public function testReportSkipsWhenArrayFilterMatches(): void
    {
        $reported = [];
        $reporter = $this->reporter($reported, new NonReportableErrors([[RuntimeException::class, InvalidArgumentException::class]]));
        $reporter->report(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), $this->context());
        $reporter->report(new ErrorOccurrence(new InvalidArgumentException(), 'ERR-test'), $this->context());
        $reporter->report(new ErrorOccurrence($shouldReport = new LogicException(), 'ERR-test'), $this->context());

        self::assertSame([$shouldReport], $reported);
    }

    public function testReportSkipsWhenCallableFilterReturnsTrue(): void
    {
        $reported = [];
        $reporter = $this->reporter($reported, new NonReportableErrors([static fn(ErrorOccurrence $e, ErrorContextInterface $c): bool => $e->exception->getCode() < 500]));
        $reporter->report(new ErrorOccurrence(new RuntimeException('client error', 400), 'ERR-test'), $this->context());
        $reporter->report(new ErrorOccurrence(new RuntimeException('not found', 404), 'ERR-test'), $this->context());
        $reporter->report(new ErrorOccurrence($shouldReport = new RuntimeException('server error', 500), 'ERR-test'), $this->context());

        self::assertSame([$shouldReport], $reported);
    }

    /**
     * @param list<Throwable> $reported
     */
    private function reporter(array &$reported, NonReportableErrors $policy): ErrorReporter
    {
        $listeners = new ArrayErrorListenerProvider([\Throwable::class => [ErrorListener::createFrom(
            static function (ErrorEventInterface $event) use (&$reported): void {
                $reported[] = $event->error->exception;
            },
        )]]);

        return new ErrorReporter($listeners, $policy);
    }

    private function context(): CliContext
    {
        return new CliContext(new ArrayInput([]), new BufferedOutput());
    }
}
