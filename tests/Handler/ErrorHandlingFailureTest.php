<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Handler;

use Componenta\Error\Context\HttpContext;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorId\ErrorIdGeneratorInterface;
use Componenta\Error\Handler\HttpErrorHandler;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Reporter\ErrorReporterInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Reporter\ErrorReporter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorHandlingFailureTest extends TestCase
{
    public function testIndependentEmergencyResponseAndOriginalErrorReporting(): void
    {
        $original = new RuntimeException('original sensitive message');
        $renderer = $this->createMock(ErrorRendererInterface::class);
        $renderer->expects(self::once())->method('render')->willThrowException(new RuntimeException('broken renderer'));
        $id = $this->createStub(ErrorIdGeneratorInterface::class);
        $id->method('generate')->willThrowException(new RuntimeException('broken generator'));
        $reported = [];
        $listeners = new \Componenta\Error\Event\ErrorListenerProvider();
        $listeners->addListener(ErrorListener::createFrom(static function (ErrorEventInterface $event) use (&$reported): void {
            $reported[] = $event;
        }));

        $reporter = new ErrorReporter($listeners);
        $reporter->addNonReportableError(static fn () => throw new RuntimeException('broken filter'));
        $handler = HttpErrorHandler::createDefaults(new Psr17Factory(), $renderer, $reporter, $id);
        $response = $handler->handle($original, new HttpContext(new ServerRequest('GET', '/')));

        self::assertSame(500, $response->getStatusCode());
        self::assertCount(1, $reported);
        self::assertSame($original, $reported[0]->exception);
        $errorId = $reported[0]->errorId;
        self::assertIsString($errorId);
        self::assertMatchesRegularExpression('/^ERR-[0-9A-F]{32}-[0-9]+$/', $errorId);
        self::assertSame("An unexpected error occurred\nError ID: " . $errorId, (string) $response->getBody());
        self::assertSame($errorId, $response->getHeaderLine('X-Error-ID'));
    }

    public function testCustomReporterCannotReplaceResponse(): void
    {
        $reporter = $this->createStub(ErrorReporterInterface::class);
        $reporter->method('report')->willThrowException(new RuntimeException('broken reporter'));
        $handler = HttpErrorHandler::createDefaults(new Psr17Factory(), reporter: $reporter);
        $response = $handler->handle(new RuntimeException('original'), new HttpContext(new ServerRequest('GET', '/')));

        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('broken reporter', (string) $response->getBody());
    }
}
