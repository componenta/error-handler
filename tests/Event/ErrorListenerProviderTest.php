<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Event;

use Componenta\Error\Context\HttpContext;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\ErrorListenerProvider;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[TestDox('ErrorListenerProvider')]
final class ErrorListenerProviderTest extends TestCase
{
    public function testGetListenersReturnsListenersSortedByPriorityDescending(): void
    {
        $order = [];

        $provider = new ErrorListenerProvider();
        $provider->addListener(ErrorListener::createFrom(
            function () use (&$order): void {
                $order[] = 'low';
            }
        ));
        $provider->addListener(ErrorListener::createFrom(
            function () use (&$order): void {
                $order[] = 'high';
            }
        ), priority: 100);
        $provider->addListener(ErrorListener::createFrom(
            function () use (&$order): void {
                $order[] = 'medium';
            }
        ), priority: 50);

        foreach ($provider->getListeners() as $listener) {
            $listener->handleEvent($this->createMock(ErrorEventInterface::class));
        }

        self::assertSame(['high', 'medium', 'low'], $order);
    }

    public function testMergeAddsListenersFromAnotherProvider(): void
    {
        $order = [];

        $provider1 = new ErrorListenerProvider();
        $provider1->addListener(ErrorListener::createFrom(
            function () use (&$order): void {
                $order[] = 'first';
            }
        ));

        $provider2 = new ErrorListenerProvider();
        $provider2->addListener(ErrorListener::createFrom(
            function () use (&$order): void {
                $order[] = 'second';
            }
        ));

        $provider1->merge($provider2);

        foreach ($provider1->getListeners() as $listener) {
            $listener->handleEvent($this->createMock(ErrorEventInterface::class));
        }

        self::assertCount(2, $order);
        self::assertContains('first', $order);
        self::assertContains('second', $order);
    }

    public function testMergeDoesNotDuplicateSameListenerInstance(): void
    {
        $called = 0;
        $listener = ErrorListener::createFrom(
            static function () use (&$called): void {
                $called++;
            },
        );

        $provider1 = new ErrorListenerProvider();
        $provider1->addListener($listener);

        $provider2 = new ErrorListenerProvider();
        $provider2->addListener($listener);

        $provider1->merge($provider2);

        foreach ($provider1->getListeners() as $mergedListener) {
            $mergedListener->handleEvent($this->createMock(ErrorEventInterface::class));
        }

        self::assertSame(1, $called);
    }

    public function testCreateFromReturnsProvidedProvider(): void
    {
        $provider = new ErrorListenerProvider();

        $result = ErrorListenerProvider::createFrom($provider);

        self::assertNotSame($provider, $result);
    }

    public function testProvideForReturnsOnlyListenersThatSupportException(): void
    {
        $order = [];

        $provider = new ErrorListenerProvider();

        $provider->addListener(ErrorListener::createFrom(
            function () use (&$order): void {
                $order[] = 'runtime';
            },
            fn(\Throwable $e, ErrorContextInterface $c): bool => $e instanceof RuntimeException,
        ));

        $provider->addListener(ErrorListener::createFrom(
            function () use (&$order): void {
                $order[] = 'invalid';
            },
            fn(\Throwable $e, ErrorContextInterface $c): bool => $e instanceof \InvalidArgumentException,
        ));

        $provider->addListener(ErrorListener::createFrom(
            function () use (&$order): void {
                $order[] = 'all';
            },
        ));

        $context = new HttpContext(new ServerRequest('GET', '/'));

        $listeners = $provider->provideFor(new RuntimeException(), $context);

        self::assertCount(2, $listeners);

        foreach ($listeners as $listener) {
            $listener->handleEvent($this->createMock(ErrorEventInterface::class));
        }

        self::assertContains('runtime', $order);
        self::assertContains('all', $order);
        self::assertNotContains('invalid', $order);
    }
}
