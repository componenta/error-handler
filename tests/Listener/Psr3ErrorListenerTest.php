<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Listener;

use Componenta\Error\Context\CliContext;
use Componenta\Error\Context\ErrorContextAttribute;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Event\ErrorEvent;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\Provider\ArrayErrorListenerProvider;
use Componenta\Error\Handler\HttpErrorHandler;
use Componenta\Error\Listener\Psr3ErrorListener;
use Componenta\Error\Reporter\Attribute\NonReportable;
use Componenta\Error\Reporter\ErrorReporter;
use Componenta\Error\Reporter\ListenerExceptionPolicy;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Throwable;

#[NonReportable]
final class ExpectedLoggedFailure extends RuntimeException {}

final class Psr3ErrorListenerTest extends TestCase
{
    public function testDefaultHttpRecordDoesNotForwardConfidentialData(): void
    {
        $request = (new ServerRequest('POST', '/private?token=query-secret', [
            'Authorization' => 'Bearer header-secret',
            'Cookie' => 'session=cookie-secret',
        ], 'body-secret'))->withParsedBody(['password' => 'parsed-secret']);
        $context = new HttpContext($request, [
            ErrorContextAttribute::HTTP_STATUS_CODE => 503,
            'private' => 'context-secret',
        ]);
        $exception = new RuntimeException("message-secret\nfake record", 404, new RuntimeException('previous-secret'));
        $event = $this->event($exception, $context);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('log')->with(
            LogLevel::ERROR,
            'Application error {error_id} ({exception_type})',
            [
                'error_id' => 'ERR-test',
                'exception_type' => RuntimeException::class,
                'timestamp' => '2026-02-03T04:05:06.123456+03:00',
                'transport' => 'http',
                'status' => 503,
            ],
        );

        $listener = new Psr3ErrorListener($logger);
        self::assertTrue($listener->supports($event->error, $context));
        $listener->handleEvent($event);
    }

    #[DataProvider('throwableTypes')]
    public function testExceptionForwardingUsesOriginalObjectAndStandardContextKey(Throwable $exception, string $level): void
    {
        $event = $this->event($exception, CliContext::fromArgv());
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('log')->willReturnCallback(
            static function (mixed $actualLevel, string|\Stringable $message, array $record) use ($exception, $level): void {
                self::assertSame($level, $actualLevel);
                self::assertSame('Application error {error_id} ({exception_type})', $message);
                self::assertSame($exception, $record['exception']);
                self::assertSame(get_debug_type($exception), $record['exception_type']);
                self::assertSame('ERR-test', $record['error_id']);
                self::assertSame('cli', $record['transport']);
                self::assertArrayNotHasKey('status', $record);
                self::assertArrayNotHasKey('message', $record);
            },
        );

        (new Psr3ErrorListener($logger, includeException: true))->handleEvent($event);
    }

    public static function throwableTypes(): iterable
    {
        yield 'exception' => [new RuntimeException('private'), LogLevel::ERROR];
        yield 'engine error' => [new \TypeError('private'), LogLevel::CRITICAL];
    }

    #[DataProvider('statuses')]
    public function testStatusIsIncludedOnlyWhenItIsAValidInteger(mixed $status, bool $expected): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('log')->willReturnCallback(
            static function (mixed $level, string|\Stringable $message, array $record) use ($expected, $status): void {
                self::assertSame(LogLevel::ERROR, $level);
                self::assertSame($expected, array_key_exists('status', $record));
                if ($expected) {
                    self::assertSame($status, $record['status']);
                }
            },
        );
        $event = $this->event(
            new RuntimeException('not an HTTP status', 404),
            new HttpContext(new ServerRequest('GET', '/'), [ErrorContextAttribute::HTTP_STATUS_CODE => $status]),
        );
        (new Psr3ErrorListener($logger))->handleEvent($event);
    }

    public static function statuses(): iterable
    {
        yield 'actual error status' => [404, true];
        yield 'actual custom response status' => [200, true];
        yield 'missing' => [null, false];
        yield 'numeric string' => ['500', false];
        yield 'too low' => [99, false];
        yield 'too high' => [600, false];
    }

    public function testOtherContextAndAnonymousThrowableDoNotExposeInternalPathOrReadAttributes(): void
    {
        $context = $this->createMock(ErrorContextInterface::class);
        $context->expects(self::never())->method('getAttribute');
        $context->expects(self::never())->method('getAttributes');
        $exception = new class extends RuntimeException {};
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('log')->with(
            LogLevel::ERROR,
            'Application error {error_id} ({exception_type})',
            [
                'error_id' => 'ERR-test',
                'exception_type' => 'RuntimeException@anonymous',
                'timestamp' => '2026-02-03T04:05:06.123456+03:00',
            ],
        );

        (new Psr3ErrorListener($logger))->handleEvent($this->event($exception, $context));
    }

    public function testBrokenHttpMetadataDoesNotPreventLogging(): void
    {
        $context = $this->createStub(HttpErrorContextInterface::class);
        $context->method('getAttribute')->willThrowException(new RuntimeException('metadata failed'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('log')->with(
            LogLevel::ERROR,
            'Application error {error_id} ({exception_type})',
            [
                'error_id' => 'ERR-test',
                'exception_type' => RuntimeException::class,
                'timestamp' => '2026-02-03T04:05:06.123456+03:00',
                'transport' => 'http',
            ],
        );
        (new Psr3ErrorListener($logger))->handleEvent($this->event(new RuntimeException(), $context));
    }

    public function testDefaultPolicyExcludesMarkedErrorsBeforeLoggerOrClockIsCalled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('log');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $reporter = new ErrorReporter(
            new ArrayErrorListenerProvider([Throwable::class => [new Psr3ErrorListener($logger)]]),
            clock: $clock,
        );
        $reporter->report(new ErrorOccurrence(new ExpectedLoggedFailure(), 'ERR-excluded'), CliContext::fromArgv());
    }

    public function testLoggerFailureDoesNotPreventOtherListenersOrReplaceHttpResponse(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('log')->willThrowException(new RuntimeException('logger unavailable'));
        $events = [];
        $next = ErrorListener::createFrom(static function (ErrorEventInterface $event) use (&$events): void {
            $events[] = $event;
        });
        $reporter = new ErrorReporter(new ArrayErrorListenerProvider([
            Throwable::class => [new Psr3ErrorListener($logger), $next],
        ]));
        $error = new ErrorOccurrence(new RuntimeException('private original'), 'ERR-primary');
        $response = HttpErrorHandler::createDefaults(new Psr17Factory(), reporter: $reporter)->handle(
            $error,
            new HttpContext(new ServerRequest('GET', '/', ['Accept' => 'application/json'])),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame($error->id, $response->getHeaderLine('X-Error-ID'));
        self::assertCount(1, $events);
        self::assertSame($error, $events[0]->error);
    }

    public function testThrowPolicyPreservesOriginalLoggerFailure(): void
    {
        $failure = new RuntimeException('logger failure');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('log')->willThrowException($failure);
        $reporter = new ErrorReporter(
            new ArrayErrorListenerProvider([Throwable::class => [new Psr3ErrorListener($logger)]]),
            listenerExceptionPolicy: ListenerExceptionPolicy::Throw,
        );

        try {
            $reporter->report(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), CliContext::fromArgv());
            self::fail('Logger failure must follow the reporter failure policy.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    public function testLoggerReentrancyUsesTheExistingReporterGuard(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $reporter = new ErrorReporter(new ArrayErrorListenerProvider([
            Throwable::class => [new Psr3ErrorListener($logger)],
        ]));
        $calls = [];
        $context = CliContext::fromArgv();
        $logger->expects(self::exactly(2))->method('log')->willReturnCallback(
            static function (mixed $level, string|\Stringable $message, array $record) use ($reporter, $context, &$calls): void {
                $calls[] = $record['error_id'];
                if (count($calls) === 1) {
                    $reporter->report(new ErrorOccurrence(new RuntimeException(), 'ERR-nested'), $context);
                }
            },
        );

        $reporter->report(new ErrorOccurrence(new RuntimeException(), 'ERR-first'), $context);
        $reporter->report(new ErrorOccurrence(new RuntimeException(), 'ERR-second'), $context);
        self::assertSame(['ERR-first', 'ERR-second'], $calls);
    }

    private function event(Throwable $exception, ErrorContextInterface $context): ErrorEvent
    {
        return new ErrorEvent(
            new ErrorOccurrence($exception, 'ERR-test'),
            $context,
            new DateTimeImmutable('2026-02-03T04:05:06.123456+03:00'),
        );
    }
}
