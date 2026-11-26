<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Reporter;

use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Clock\UtcClock;
use Componenta\Error\Context\CliContext;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\Provider\ArrayErrorListenerProvider;
use Componenta\Error\Event\ErrorListenerProviderInterface;
use Componenta\Error\Factory\ErrorReporterFactory;
use Componenta\Error\Reporter\ErrorReporter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class ReporterClockTest extends TestCase
{
    public function testReporterUsesTheInjectedClockAndSharesOneTimestamp(): void
    {
        $instant = new DateTimeImmutable('2026-02-03T04:05:06.123456+03:00');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::once())->method('now')->willReturn($instant);
        $listeners = [];
        $events = [];
        for ($i = 0; $i < 2; ++$i) {
            $listeners[] = ErrorListener::createFrom(
                static function (ErrorEventInterface $event) use (&$events): void {
                    $events[] = $event;
                },
            );
        }

        (new ErrorReporter(new ArrayErrorListenerProvider([\Throwable::class => $listeners]), clock: $clock))->report(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), CliContext::fromArgv());
        self::assertCount(2, $events);
        self::assertSame($events[0], $events[1]);
        self::assertSame($instant, $events[0]->timestamp);
    }

    public function testFactoryUsesTheContainerClock(): void
    {
        $instant = new DateTimeImmutable('2026-02-03T04:05:06+00:00');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::once())->method('now')->willReturn($instant);
        $seen = null;
        $listeners = new ArrayErrorListenerProvider([\Throwable::class => [ErrorListener::createFrom(
            static function (ErrorEventInterface $event) use (&$seen): void {
                $seen = $event->timestamp;
            },
        )]]);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => in_array($id, [ErrorListenerProviderInterface::class, ClockInterface::class], true));
        $container->method('get')->willReturnMap([[ErrorListenerProviderInterface::class, $listeners], [ClockInterface::class, $clock]]);

        (new ErrorReporterFactory())($container)->report(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), CliContext::fromArgv());
        self::assertSame($instant, $seen);
    }

    public function testDefaultClockReturnsUtcTime(): void
    {
        $before = time();
        $now = (new UtcClock())->now();
        self::assertSame('UTC', $now->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before, $now->getTimestamp());
        self::assertLessThanOrEqual(time(), $now->getTimestamp());
    }
}
