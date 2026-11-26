<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Reporter;

use Componenta\Error\Context\CliContext;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\Provider\ArrayErrorListenerProvider;
use Componenta\Error\Reporter\ErrorReporter;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class FailureDiagnosticTest extends TestCase
{
    public function testSecondaryDiagnosticNeverLeaksAnonymousClassPathOrExceptionMessage(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'error-failure-');
        self::assertIsString($path);
        $previous = ini_get('error_log');
        ini_set('error_log', $path);
        try {
            $original = new class('private original') extends RuntimeException {};
            $secondary = new class('private secondary') extends RuntimeException {};
            $listener = ErrorListener::createFrom(static function () use ($secondary): void { throw $secondary; });
            $reporter = new ErrorReporter(new ArrayErrorListenerProvider([Throwable::class => [$listener]]));
            $reporter->report(new ErrorOccurrence($original, 'ERR-test'), CliContext::fromArgv());

            $log = file_get_contents($path);
            self::assertIsString($log);
            self::assertStringContainsString('RuntimeException@anonymous (while handling RuntimeException@anonymous)', $log);
            self::assertStringNotContainsString(__FILE__, $log);
            self::assertStringNotContainsString("\0", $log);
            self::assertStringNotContainsString('private original', $log);
            self::assertStringNotContainsString('private secondary', $log);
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
            unlink($path);
        }
    }
}
