<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Reporter;

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
        $listeners = new ErrorListenerProvider();
        $reporter = new ErrorReporter($listeners);
        $calls = [];
        $listeners->addListener(ErrorListener::createFrom(
            static function (ErrorEventInterface $event) use ($reporter, &$calls): void {
                $calls[] = $event->exception->getMessage();
                // Bound the test itself so the old implementation fails safely.
                if (count($calls) === 1) {
                    $reporter->report(new RuntimeException('nested'), $event->context);
                }
            },
        ));

        $reporter->report(new RuntimeException('first'), $this->context());
        $reporter->report(new RuntimeException('second'), $this->context());

        self::assertSame(['first', 'second'], $calls);
    }

    public function testIndependentFibersCanReportWhileAnotherIsSuspended(): void
    {
        $listeners = new ErrorListenerProvider();
        $reporter = new ErrorReporter($listeners);
        $calls = [];
        $listeners->addListener(ErrorListener::createFrom(
            static function (ErrorEventInterface $event) use (&$calls): void {
                $message = $event->exception->getMessage();
                $calls[] = $message;
                if ($message === 'fiber-a') {
                    Fiber::suspend();
                }
            },
        ));
        $context = $this->context();
        $a = new Fiber(static fn () => $reporter->report(new RuntimeException('fiber-a'), $context));
        $b = new Fiber(static fn () => $reporter->report(new RuntimeException('fiber-b'), $context));

        $a->start();
        $b->start();
        $a->resume();
        $reporter->report(new RuntimeException('main'), $context);

        self::assertSame(['fiber-a', 'fiber-b', 'main'], $calls);
    }

    public function testThrowPolicyRestoresGuardAfterFailure(): void
    {
        $listeners = new ErrorListenerProvider();
        $reporter = new ErrorReporter($listeners, ListenerExceptionPolicy::Throw);
        $calls = 0;
        $failure = new RuntimeException('listener failure');
        $listeners->addListener(ErrorListener::createFrom(
            static function () use (&$calls, $failure): void {
                if (++$calls === 1) {
                    throw $failure;
                }
            },
        ));

        try {
            $reporter->report(new RuntimeException('first'), $this->context());
            self::fail('Expected the listener failure to propagate.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }

        $reporter->report(new RuntimeException('second'), $this->context());
        self::assertSame(2, $calls);
    }

    private function context(): CliContext
    {
        return new CliContext(new ArrayInput([]), new BufferedOutput());
    }
}
