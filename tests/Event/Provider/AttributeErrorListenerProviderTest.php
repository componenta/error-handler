<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Event\Provider;

use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Event\Attribute\ListenTo;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListenerInterface;
use Componenta\Error\Event\Provider\AttributeErrorListenerProvider;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

abstract class ProviderListener implements ErrorListenerInterface
{
    public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool { return true; }
    public function handleEvent(ErrorEventInterface $event): void {}
}

#[ListenTo(Throwable::class)]
#[ListenTo(RuntimeException::class, priority: 100)]
class GeneralListener extends ProviderListener {}

#[ListenTo(RuntimeException::class, priority: 10)]
class RuntimeListener extends ProviderListener {}

#[ListenTo(\Error::class)]
class PhpErrorListener extends ProviderListener {}

#[ListenTo(\stdClass::class)]
class InvalidAttributeListener extends ProviderListener {}

class UnannotatedListener extends GeneralListener {}

final class AttributeErrorListenerProviderTest extends TestCase
{
    public function testIndexResolvesOnlyMatchingClassesOncePerTraversalInPriorityOrder(): void
    {
        $calls = [];
        $provider = new AttributeErrorListenerProvider(
            [RuntimeListener::class, GeneralListener::class, PhpErrorListener::class, GeneralListener::class, UnannotatedListener::class],
            static function (string $class) use (&$calls): ErrorListenerInterface {
                $calls[] = $class;
                return new $class();
            },
        );
        $error = new \OverflowException();
        $first = $provider->provideFor($error);
        self::assertSame([], $calls);
        $one = iterator_to_array($first);
        $two = iterator_to_array($provider->provideFor($error));
        self::assertSame([GeneralListener::class, RuntimeListener::class], array_map(get_class(...), $one));
        self::assertSame([GeneralListener::class, RuntimeListener::class], array_map(get_class(...), $two));
        self::assertNotSame($one[0], $two[0]);
        self::assertSame([GeneralListener::class, RuntimeListener::class, GeneralListener::class, RuntimeListener::class], $calls);
        self::assertSame([GeneralListener::class, PhpErrorListener::class], array_map(get_class(...), iterator_to_array($provider->provideFor(new \TypeError()))));
    }

    public function testResolverFailureDoesNotDiscardRemainingMatches(): void
    {
        $listener = new RuntimeListener();
        $provider = new AttributeErrorListenerProvider(
            [GeneralListener::class, RuntimeListener::class],
            static function (string $class) use ($listener): ErrorListenerInterface {
                if ($class === GeneralListener::class) {
                    throw new RuntimeException('resolver failure');
                }
                return $listener;
            },
        );
        self::assertSame([$listener], iterator_to_array($provider->provideFor(new RuntimeException())));
    }

    public function testResolverFailureIsNotCachedAndLaterTraversalCanRecover(): void
    {
        $calls = 0;
        $listener = new GeneralListener();
        $provider = new AttributeErrorListenerProvider([GeneralListener::class], static function () use (&$calls, $listener): ErrorListenerInterface {
            if (++$calls === 1) {
                throw new RuntimeException('transient resolver failure');
            }
            return $listener;
        });
        $error = new RuntimeException();
        self::assertSame([], iterator_to_array($provider->provideFor($error)));
        self::assertSame([$listener], iterator_to_array($provider->provideFor($error)));
        self::assertSame(2, $calls);
    }

    public function testInvalidAttributeFailsAtConstructionWithoutResolvingService(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AttributeErrorListenerProvider([InvalidAttributeListener::class], static fn () => throw new LogicException('not resolved'));
    }

    public function testInvalidListenerClassFailsAtConstruction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AttributeErrorListenerProvider([ProviderListener::class], static fn () => throw new LogicException('not resolved'));
    }

    public function testResolverReturningWrongClassIsRejected(): void
    {
        $provider = new AttributeErrorListenerProvider([RuntimeListener::class], static fn () => new GeneralListener());
        self::assertSame([], iterator_to_array($provider->provideFor(new RuntimeException())));
    }
}
