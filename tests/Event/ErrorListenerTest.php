<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Event;

use Componenta\Error\Context\CliContext;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorEvent;
use Componenta\Error\Event\ErrorListener;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

#[TestDox('ErrorListener')]
final class ErrorListenerTest extends TestCase
{
    public function testHandleEventInvokesCallbackWithEvent(): void
    {
        $receivedEvent = null;
        $listener = ErrorListener::createFrom(function (ErrorEventInterface $event) use (&$receivedEvent): void {
            $receivedEvent = $event;
        });

        $event = new ErrorEvent(new RuntimeException(), CliContext::fromArgv());
        $listener->handleEvent($event);

        self::assertSame($event, $receivedEvent);
    }

    public function testSupportsReturnsTrueWithoutSupportsCallback(): void
    {
        $listener = ErrorListener::createFrom(fn(ErrorEventInterface $e) => null);

        self::assertTrue($listener->supports(new RuntimeException(), CliContext::fromArgv()));
    }

    public function testSupportsReturnsFalseWhenCallbackReturnsFalse(): void
    {
        $listener = ErrorListener::createFrom(
            callback: fn(ErrorEventInterface $e) => null,
            supports: fn(Throwable $e, ErrorContextInterface $c): bool => false,
        );

        self::assertFalse($listener->supports(new RuntimeException(), CliContext::fromArgv()));
    }

    public function testSupportsWithStringFiltersByInstanceof(): void
    {
        $listener = ErrorListener::createFrom(
            callback: fn(ErrorEventInterface $e) => null,
            supports: RuntimeException::class,
        );

        self::assertTrue($listener->supports(new RuntimeException(), CliContext::fromArgv()));
        self::assertFalse($listener->supports(new InvalidArgumentException(), CliContext::fromArgv()));
    }

    public function testSupportsWithArrayFiltersByMultipleClasses(): void
    {
        $listener = ErrorListener::createFrom(
            callback: fn(ErrorEventInterface $e) => null,
            supports: [RuntimeException::class, InvalidArgumentException::class],
        );

        self::assertTrue($listener->supports(new RuntimeException(), CliContext::fromArgv()));
        self::assertTrue($listener->supports(new InvalidArgumentException(), CliContext::fromArgv()));
        self::assertFalse($listener->supports(new LogicException(), CliContext::fromArgv()));
    }
}