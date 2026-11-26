<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Reporter;

use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Context\CliContext;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\ErrorListenerProvider;
use Componenta\Error\Reporter\ErrorReporter;
use Componenta\Error\Reporter\ListenerExceptionPolicy;
use Fiber;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ReporterReentrancyTest extends TestCase
{
    public function testRecursiveListenerDoesNotDispatchAgainAndLaterReportsStillWork(): void
    {
        $reporter = null;
        $calls = [];
        $listeners = new ErrorListenerProvider([ErrorListener::createFrom(
            static function (ErrorEventInterface $event) use (&$reporter, &$calls): void {
                $calls[] = $event->error->exception->getMessage();
                // Bound the test itself so the old implementation fails safely.
                if (count($calls) === 1) {
                    $reporter->report(new ErrorOccurrence(new RuntimeException('nested'), 'ERR-test'), $event->context);
                }
            },
        )]);
        $reporter = new ErrorReporter($listeners);

        $reporter->report(new ErrorOccurrence(new RuntimeException('first'), 'ERR-test'), $this->context());
        $reporter->report(new ErrorOccurrence(new RuntimeException('second'), 'ERR-test'), $this->context());

        self::assertSame(['first', 'second'], $calls);
    }

    public function testIndependentFibersCanReportWhileAnotherIsSuspended(): void
    {
        $calls = [];
        $listeners = new ErrorListenerProvider([ErrorListener::createFrom(
            static function (ErrorEventInterface $event) use (&$calls): void {
                $message = $event->error->exception->getMessage();
                $calls[] = $message;
                if ($message === 'fiber-a') {
                    Fiber::suspend();
                }
            },
        )]);
        $reporter = new ErrorReporter($listeners);
        $context = $this->context();
        $a = new Fiber(static fn () => $reporter->report(new ErrorOccurrence(new RuntimeException('fiber-a'), 'ERR-test'), $context));
        $b = new Fiber(static fn () => $reporter->report(new ErrorOccurrence(new RuntimeException('fiber-b'), 'ERR-test'), $context));

        $a->start();
        $b->start();
        $a->resume();
        $reporter->report(new ErrorOccurrence(new RuntimeException('main'), 'ERR-test'), $context);

        self::assertSame(['fiber-a', 'fiber-b', 'main'], $calls);
    }

    public function testThrowPolicyRestoresGuardAfterFailure(): void
    {
        $calls = 0;
        $failure = new RuntimeException('listener failure');
        $listeners = new ErrorListenerProvider([ErrorListener::createFrom(
            static function () use (&$calls, $failure): void {
                if (++$calls === 1) {
                    throw $failure;
                }
            },
        )]);
        $reporter = new ErrorReporter($listeners, listenerExceptionPolicy: ListenerExceptionPolicy::Throw);

        try {
            $reporter->report(new ErrorOccurrence(new RuntimeException('first'), 'ERR-test'), $this->context());
            self::fail('Expected the listener failure to propagate.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }

        $reporter->report(new ErrorOccurrence(new RuntimeException('second'), 'ERR-test'), $this->context());
        self::assertSame(2, $calls);
    }

    private function context(): CliContext
    {
        return new CliContext(new ArrayInput([]), new BufferedOutput());
    }
}
