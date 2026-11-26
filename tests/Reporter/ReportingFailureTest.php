<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Reporter;

use Componenta\Error\Context\CliContext;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\Provider\ArrayErrorListenerProvider;
use Componenta\Error\Reporter\ErrorReporter;
use Componenta\Error\Reporter\Policy\NonReportableErrors;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;

final class ReportingFailureTest extends TestCase
{
    public function testExcludedErrorDoesNotCreateEventOrReadClock(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $listener = $this->createMock(\Componenta\Error\Event\ErrorListenerInterface::class);
        $listener->expects(self::never())->method('supports');
        $listener->expects(self::never())->method('handleEvent');

        $reporter = new ErrorReporter(new ArrayErrorListenerProvider([\Throwable::class => [$listener]]), new NonReportableErrors([RuntimeException::class]), $clock);
        $reporter->report(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), CliContext::fromArgv());
    }

    public function testBrokenPolicyClockAndListenerDoNotPreventSubsequentListener(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::once())->method('now')->willThrowException(new RuntimeException('broken clock'));
        $events = [];
        $listeners = new ArrayErrorListenerProvider([\Throwable::class => [
            ErrorListener::createFrom(static function (): void { throw new RuntimeException('broken listener'); }),
            ErrorListener::createFrom(static function (ErrorEventInterface $event) use (&$events): void { $events[] = $event; }),
        ]]);
        $policy = new NonReportableErrors([static fn () => throw new RuntimeException('broken policy')]);
        $error = new ErrorOccurrence(new RuntimeException(), 'ERR-test');
        (new ErrorReporter($listeners, $policy, $clock))->report($error, CliContext::fromArgv());

        self::assertCount(1, $events);
        self::assertSame($error, $events[0]->error);
        self::assertSame('UTC', $events[0]->timestamp->getTimezone()->getName());
    }
}
