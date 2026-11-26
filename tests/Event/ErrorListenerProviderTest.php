<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Event;

use Componenta\Error\Context\CliContext;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\ErrorListenerProvider;
use Componenta\Error\Reporter\ErrorReporter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorListenerProviderTest extends TestCase
{
    public function testGetListenersReturnsListenersSortedByPriorityDescending(): void
    {
        $low = ErrorListener::createFrom(static function (): void {});
        $high = ErrorListener::createFrom(static function (): void {});
        $medium = ErrorListener::createFrom(static function (): void {});
        $provider = new ErrorListenerProvider([
            $low,
            ['listener' => $high, 'priority' => 100],
            ['listener' => $medium, 'priority' => 50],
        ]);

        self::assertSame([$high, $medium, $low], iterator_to_array($provider->getListeners()));
    }

    public function testEqualPrioritiesRetainOrderAndDuplicatesUseFirstRegistration(): void
    {
        $first = ErrorListener::createFrom(static function (): void {});
        $second = ErrorListener::createFrom(static function (): void {});
        $third = ErrorListener::createFrom(static function (): void {});
        $provider = new ErrorListenerProvider([
            $first,
            $second,
            ['listener' => $first, 'priority' => 100],
            ['listener' => $third, 'priority' => -1],
        ]);

        self::assertSame([$first, $second, $third], iterator_to_array($provider->getListeners()));
        self::assertSame([$first, $second, $third], iterator_to_array($provider->getListeners()));
    }

    public function testListenerSelectionBelongsToReporterNotProvider(): void
    {
        $order = [];
        $runtime = ErrorListener::createFrom(static function () use (&$order): void {
            $order[] = 'runtime';
        }, RuntimeException::class);
        $invalid = ErrorListener::createFrom(static function () use (&$order): void {
            $order[] = 'invalid';
        }, InvalidArgumentException::class);
        $all = ErrorListener::createFrom(static function () use (&$order): void {
            $order[] = 'all';
        });
        $provider = new ErrorListenerProvider([$runtime, $invalid, $all]);
        self::assertSame([$runtime, $invalid, $all], iterator_to_array($provider->getListeners()));

        (new ErrorReporter($provider))->report(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), CliContext::fromArgv());

        self::assertSame(['runtime', 'all'], $order);
    }

    public function testProviderDoesNotRetainTheMutableInputArray(): void
    {
        $listener = ErrorListener::createFrom(static function (): void {});
        $source = [$listener];
        $provider = new ErrorListenerProvider($source);
        $source[] = ErrorListener::createFrom(static function (): void {});

        self::assertSame([$listener], iterator_to_array($provider->getListeners()));
    }
}
