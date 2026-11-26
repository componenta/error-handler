<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Event\Provider;

use Componenta\Error\Context\CliContext;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\ErrorListenerInterface;
use Componenta\Error\Event\ErrorListenerProviderInterface;
use Componenta\Error\Event\Provider\ArrayErrorListenerProvider;
use Componenta\Error\Event\Provider\CompositeErrorListenerProvider;
use Componenta\Error\Reporter\ErrorReporter;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Throwable;

final class CompositeErrorListenerProviderTest extends TestCase
{
    public function testProviderOrderAndObjectIdentityDeduplication(): void
    {
        $first = ErrorListener::createFrom(static function (): void {});
        $second = ErrorListener::createFrom(static function (): void {});
        $third = ErrorListener::createFrom(static function (): void {});
        $one = new ArrayErrorListenerProvider([Throwable::class => [$first, $second]]);
        $two = new ArrayErrorListenerProvider([RuntimeException::class => [
            ['listener' => $third, 'priority' => 100],
            $second,
        ]]);
        $provider = new CompositeErrorListenerProvider([$one, $two, $one]);
        $error = new RuntimeException();
        self::assertSame([$first, $second, $third], iterator_to_array($provider->provideFor($error)));
        self::assertSame([$first, $second, $third], iterator_to_array($provider->provideFor($error)));
    }

    public function testEagerAndDeferredProviderFailuresDoNotStopOtherProviders(): void
    {
        $first = ErrorListener::createFrom(static function (): void {});
        $second = ErrorListener::createFrom(static function (): void {});
        $broken = new class implements ErrorListenerProviderInterface {
            public function provideFor(Throwable $exception): iterable { throw new RuntimeException('eager'); }
        };
        $partial = new readonly class($first) implements ErrorListenerProviderInterface {
            public function __construct(private ErrorListenerInterface $listener) {}
            public function provideFor(Throwable $exception): iterable
            {
                yield $this->listener;
                throw new RuntimeException('deferred');
            }
        };
        $provider = new CompositeErrorListenerProvider([
            $broken,
            $partial,
            new ArrayErrorListenerProvider([Throwable::class => [$first, $second]]),
        ]);
        self::assertSame([$first, $second], iterator_to_array($provider->provideFor(new RuntimeException())));
    }

    public function testMalformedEntryDoesNotDiscardLaterValidListeners(): void
    {
        $first = ErrorListener::createFrom(static function (): void {});
        $second = ErrorListener::createFrom(static function (): void {});
        $third = ErrorListener::createFrom(static function (): void {});
        $partial = new readonly class($first, $second) implements ErrorListenerProviderInterface {
            public function __construct(private ErrorListenerInterface $first, private ErrorListenerInterface $second) {}
            public function provideFor(Throwable $exception): iterable
            {
                yield $this->first;
                yield null;
                yield $this->second;
                yield $this->first;
            }
        };
        $provider = new CompositeErrorListenerProvider([
            $partial,
            new ArrayErrorListenerProvider([Throwable::class => [$third]]),
        ]);
        self::assertSame([$first, $second, $third], iterator_to_array($provider->provideFor(new RuntimeException())));
    }

    public function testReporterDoesNotReadClockWithoutSupportingListener(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $listener = $this->createMock(ErrorListenerInterface::class);
        $listener->method('supports')->willReturn(false);
        $listener->expects(self::never())->method('handleEvent');
        $provider = new ArrayErrorListenerProvider([RuntimeException::class => [$listener]]);
        $reporter = new ErrorReporter($provider, clock: $clock);
        $reporter->report(new ErrorOccurrence(new \TypeError(), 'ERR-a'), CliContext::fromArgv());
        $reporter->report(new ErrorOccurrence(new RuntimeException(), 'ERR-b'), CliContext::fromArgv());
    }

    public function testDistinctInstancesOfSameListenerClassAreNotCollapsed(): void
    {
        $first = ErrorListener::createFrom(static function (): void {});
        $second = ErrorListener::createFrom(static function (): void {});
        $provider = new CompositeErrorListenerProvider([
            new ArrayErrorListenerProvider([Throwable::class => [$first]]),
            new ArrayErrorListenerProvider([Throwable::class => [$second]]),
        ]);
        self::assertSame([$first, $second], iterator_to_array($provider->provideFor(new RuntimeException())));
    }
}
