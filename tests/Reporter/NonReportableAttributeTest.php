<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Reporter;

use Componenta\Error\Context\CliContext;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\ErrorListenerProviderInterface;
use Componenta\Error\Event\Provider\ArrayErrorListenerProvider;
use Componenta\Error\Handler\CliErrorHandler;
use Componenta\Error\Handler\HttpErrorHandler;
use Componenta\Error\Reporter\Attribute\NonReportable;
use Componenta\Error\Reporter\ErrorReporter;
use Componenta\Error\Reporter\Policy\ErrorReportingPolicyInterface;
use Componenta\Error\Reporter\Policy\NonReportableErrors;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

#[NonReportable]
class ExpectedFailure extends RuntimeException
{
}

class UnexpectedChildFailure extends ExpectedFailure
{
}

#[NonReportable]
final class ExpectedChildFailure extends ExpectedFailure
{
}

#[NonReportable]
final class ExpectedPhpError extends \Error
{
}

#[NonReportable]
#[NonReportable]
final class InvalidRepeatedMarker extends RuntimeException
{
}

final class NonReportableAttributeTest extends TestCase
{
    public function testMarkerAppliesOnlyToTheDirectlyAnnotatedThrowableClass(): void
    {
        $policy = new NonReportableErrors();
        $context = CliContext::fromArgv();

        // Repeat all decisions to cover the cached positive and negative paths.
        for ($i = 0; $i < 2; ++$i) {
            self::assertFalse($policy->shouldReport(new ErrorOccurrence(new ExpectedFailure(), 'ERR-parent'), $context));
            self::assertTrue($policy->shouldReport(new ErrorOccurrence(new UnexpectedChildFailure(), 'ERR-child'), $context));
            self::assertFalse($policy->shouldReport(new ErrorOccurrence(new ExpectedChildFailure(), 'ERR-marked-child'), $context));
            self::assertFalse($policy->shouldReport(new ErrorOccurrence(new ExpectedPhpError(), 'ERR-php'), $context));
            self::assertTrue($policy->shouldReport(new ErrorOccurrence(new RuntimeException(), 'ERR-ordinary'), $context));
            self::assertTrue($policy->shouldReport(new ErrorOccurrence(new RuntimeException(previous: new ExpectedFailure()), 'ERR-wrapper'), $context));
        }
    }

    public function testAttributeAndConfiguredRulesAreIndependentExclusions(): void
    {
        $context = CliContext::fromArgv();
        $policy = new NonReportableErrors([ExpectedFailure::class]);

        // Unlike the attribute, a configured class rule includes descendants.
        self::assertFalse($policy->shouldReport(new ErrorOccurrence(new UnexpectedChildFailure(), 'ERR-child'), $context));
        self::assertFalse($policy->shouldReport(new ErrorOccurrence(new ExpectedPhpError(), 'ERR-marked'), $context));
        self::assertTrue($policy->shouldReport(new ErrorOccurrence(new RuntimeException(), 'ERR-ordinary'), $context));
    }

    public function testCacheDoesNotFreezeContextDependentPredicates(): void
    {
        $calls = 0;
        $policy = new NonReportableErrors([
            static function (ErrorOccurrence $error, ErrorContextInterface $context) use (&$calls): bool {
                ++$calls;
                return $context->getAttribute('exclude', false);
            },
        ]);
        $context = CliContext::fromArgv();
        $error = new ErrorOccurrence(new RuntimeException(), 'ERR-context');

        self::assertTrue($policy->shouldReport($error, $context));
        self::assertFalse($policy->shouldReport($error, $context->withAttribute('exclude', true)));
        self::assertTrue($policy->shouldReport($error, $context));
        self::assertFalse($policy->shouldReport(new ErrorOccurrence(new ExpectedFailure(), 'ERR-marked'), $context));
        self::assertSame(3, $calls);
    }

    public function testMarkedErrorDoesNotQueryProviderOrClock(): void
    {
        $provider = $this->createMock(ErrorListenerProviderInterface::class);
        $provider->expects(self::never())->method('provideFor');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');

        (new ErrorReporter($provider, clock: $clock))->report(
            new ErrorOccurrence(new ExpectedFailure(), 'ERR-excluded'),
            CliContext::fromArgv(),
        );
    }

    public function testMarkerDoesNotSuppressHttpOrCliRendering(): void
    {
        $provider = $this->createMock(ErrorListenerProviderInterface::class);
        $provider->expects(self::never())->method('provideFor');
        $reporter = new ErrorReporter($provider);
        $error = new ErrorOccurrence(new ExpectedFailure(), 'ERR-visible');

        $response = HttpErrorHandler::createDefaults(new Psr17Factory(), reporter: $reporter)->handle(
            $error,
            new HttpContext(new ServerRequest('GET', '/', ['Accept' => 'application/json'])),
        );
        self::assertSame(500, $response->getStatusCode());
        self::assertSame($error->id, $response->getHeaderLine('X-Error-ID'));
        self::assertSame($error->id, json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['error']['id']);

        $output = new BufferedOutput();
        CliErrorHandler::createDefaults(reporter: $reporter)->handle($error, new CliContext(new ArrayInput([]), $output));
        self::assertStringContainsString('Error ID: ' . $error->id, $output->fetch());
    }

    public function testCustomPolicyMayReportMarkedExceptions(): void
    {
        $policy = $this->createStub(ErrorReportingPolicyInterface::class);
        $policy->method('shouldReport')->willReturn(true);
        $events = [];
        $listener = ErrorListener::createFrom(static function (ErrorEventInterface $event) use (&$events): void {
            $events[] = $event;
        });
        $error = new ErrorOccurrence(new ExpectedFailure(), 'ERR-custom-policy');
        (new ErrorReporter(new ArrayErrorListenerProvider([Throwable::class => [$listener]]), $policy))->report(
            $error,
            CliContext::fromArgv(),
        );

        self::assertCount(1, $events);
        self::assertSame($error, $events[0]->error);
    }

    public function testInvalidRepeatedMarkerFailsOpenInReporter(): void
    {
        $events = [];
        $listener = ErrorListener::createFrom(static function (ErrorEventInterface $event) use (&$events): void {
            $events[] = $event;
        });
        $reporter = new ErrorReporter(new ArrayErrorListenerProvider([Throwable::class => [$listener]]));
        $error = new ErrorOccurrence(new InvalidRepeatedMarker(), 'ERR-invalid-marker');

        $reporter->report($error, CliContext::fromArgv());

        self::assertCount(1, $events);
        self::assertSame($error, $events[0]->error);
    }
}
