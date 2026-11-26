<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Event\Provider;

use Componenta\Error\Context\CliContext;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\Provider\ArrayErrorListenerProvider;
use Componenta\Error\Reporter\ErrorReporter;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class ArrayErrorListenerProviderTest extends TestCase
{
    public function testMatchesInheritanceWithStablePriorityAndHighestMatchingDuplicate(): void
    {
        $all = ErrorListener::createFrom(static function (): void {});
        $runtime = ErrorListener::createFrom(static function (): void {});
        $tie = ErrorListener::createFrom(static function (): void {});
        $phpError = ErrorListener::createFrom(static function (): void {});
        $provider = new ArrayErrorListenerProvider([
            Throwable::class => [
                $all,
                $tie,
            ],
            RuntimeException::class => [
                ['listener' => $runtime, 'priority' => 20],
                ['listener' => $all, 'priority' => 100],
            ],
            \Error::class => [$phpError],
        ]);

        foreach ([new RuntimeException(), new \OverflowException(), new RuntimeException()] as $error) {
            self::assertSame([$all, $runtime, $tie], iterator_to_array($provider->provideFor($error)));
        }
        self::assertSame([$all, $tie], iterator_to_array($provider->provideFor(new LogicException())));
        self::assertSame([$all, $tie, $phpError], iterator_to_array($provider->provideFor(new \TypeError())));
    }

    public function testProviderReturnsIndependentSequencesAndDoesNotEvaluateLocalSupports(): void
    {
        $calls = 0;
        $listener = ErrorListener::createFrom(
            static function () use (&$calls): void { ++$calls; },
            static fn (ErrorOccurrence $error, $context): bool => $context->getAttribute('enabled', false),
        );
        $provider = new ArrayErrorListenerProvider([Throwable::class => [$listener]]);
        $error = new RuntimeException();
        $first = $provider->provideFor($error);
        self::assertSame([$listener], iterator_to_array($provider->provideFor($error)));
        self::assertSame([$listener], iterator_to_array($first));
        self::assertSame(0, $calls);

        $reporter = new ErrorReporter($provider);
        $reporter->report(new ErrorOccurrence($error, 'ERR-a'), CliContext::fromArgv());
        $reporter->report(new ErrorOccurrence($error, 'ERR-b'), CliContext::fromArgv()->withAttribute('enabled', true));
        self::assertSame(1, $calls);
    }

    public function testSnapshotDoesNotRetainInputArrayReferences(): void
    {
        $listener = ErrorListener::createFrom(static function (): void {});
        $source = [Throwable::class => [$listener]];
        $provider = new ArrayErrorListenerProvider($source);
        $source[Throwable::class][] = ErrorListener::createFrom(static function (): void {});

        self::assertSame([$listener], iterator_to_array($provider->provideFor(new RuntimeException())));
    }

    #[DataProvider('invalidRegistrations')]
    public function testRejectsInvalidRegistrations(array $registrations): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ArrayErrorListenerProvider($registrations);
    }

    public static function invalidRegistrations(): iterable
    {
        yield ['wrong-key' => [\stdClass::class => []]];
        yield ['numeric-key' => [[]]];
        yield ['wrong-group' => [Throwable::class => 'invalid']];
        yield ['wrong-listener' => [Throwable::class => ['invalid']]];
        yield ['wrong-priority' => [Throwable::class => [['listener' => ErrorListener::createFrom(static function (): void {}), 'priority' => '100']]]];
    }

    public function testEmptyProviderMatchesNothing(): void
    {
        self::assertSame([], iterator_to_array((new ArrayErrorListenerProvider())->provideFor(new RuntimeException())));
    }
}
