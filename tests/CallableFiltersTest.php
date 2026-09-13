<?php

declare(strict_types=1);

namespace Componenta\Error\Tests;

use Componenta\Error\Context\CliContext;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Reporter\ErrorReporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

final class CallableErrorFilter
{
    public static function accepts(Throwable $error, ErrorContextInterface $context): bool
    {
        return $error->getCode() === $context->getAttribute('filteredCode');
    }

    public function matches(Throwable $error, ErrorContextInterface $context): bool
    {
        return self::accepts($error, $context);
    }

    public function __invoke(Throwable $error, ErrorContextInterface $context): bool
    {
        return self::accepts($error, $context);
    }
}

function callableErrorPredicate(Throwable $error, ErrorContextInterface $context): bool
{
    return CallableErrorFilter::accepts($error, $context);
}

final class CallableFiltersTest extends TestCase
{
    #[DataProvider('filterCases')]
    public function testNonReportableFiltersReceiveTheOriginalErrorAndContext(string $form, bool $suppress): void
    {
        $error = new RuntimeException('original', 400);
        $context = new CliContext(new ArrayInput([]), new BufferedOutput(), [
            'filteredCode' => $suppress ? 400 : 500,
        ]);
        $events = [];
        $reporter = new ErrorReporter();
        $reporter->addListener(ErrorListener::createFrom(
            static function (ErrorEventInterface $event) use (&$events): void {
                $events[] = $event->exception;
            },
        ));
        $reporter->addNonReportableError(self::predicate($form));

        $reporter->report($error, $context);

        self::assertSame($suppress ? [] : [$error], $events);
    }

    #[DataProvider('filterCases')]
    public function testListenerFiltersReceiveTheOriginalErrorAndContext(string $form, bool $supports): void
    {
        $error = new RuntimeException('original', 400);
        $context = new CliContext(new ArrayInput([]), new BufferedOutput(), [
            'filteredCode' => $supports ? 400 : 500,
        ]);
        $listener = ErrorListener::createFrom(static function (): void {
        }, self::predicate($form));

        self::assertSame($supports, $listener->supports($error, $context));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function filterCases(): iterable
    {
        foreach (['closure', 'object method', 'static array', 'static string', 'function name', 'invokable'] as $form) {
            yield $form . ' accepts' => [$form, true];
            yield $form . ' rejects' => [$form, false];
        }
    }

    /** @return callable(Throwable, ErrorContextInterface): bool */
    private static function predicate(string $form): callable
    {
        return match ($form) {
            'closure' => CallableErrorFilter::accepts(...),
            'object method' => [new CallableErrorFilter(), 'matches'],
            'static array' => [CallableErrorFilter::class, 'accepts'],
            'static string' => CallableErrorFilter::class . '::accepts',
            'function name' => __NAMESPACE__ . '\callableErrorPredicate',
            'invokable' => new CallableErrorFilter(),
            default => throw new \InvalidArgumentException('Unknown filter form: ' . $form),
        };
    }
}
