<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Handler;

use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Context\CliContext;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\Handler\RenderingCliErrorHandler;
use Componenta\Error\Tests\Fixture\ErrorRendererStub;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[TestDox('RenderingCliErrorHandler')]
final class RenderingCliErrorHandlerTest extends TestCase
{
    public function testHandleWritesToOutputFromRenderer(): void
    {
        $handler = new RenderingCliErrorHandler(new ErrorRendererStub('error output'));

        $output = new BufferedOutput();
        $handler->handle(
            new ErrorOccurrence(new RuntimeException(), 'ERR-test'),
            new CliContext(new ArrayInput([]), $output),
        );

        self::assertStringContainsString('error output', $output->fetch());
    }

    public function testNullRendererLeavesOutputUntouchedAndStillAllowsReporting(): void
    {
        $output = new BufferedOutput();
        $output->write('existing');
        $context = new CliContext(new ArrayInput([]), $output);
        $error = new ErrorOccurrence(new RuntimeException(), 'ERR-silent');
        $reporter = $this->createMock(\Componenta\Error\Reporter\ErrorReporterInterface::class);
        $reporter->expects(self::once())->method('report')->with(self::identicalTo($error), self::identicalTo($context));
        $handler = \Componenta\Error\Handler\CliErrorHandler::createDefaults(
            new \Componenta\Error\Renderer\NullRenderer(),
            $reporter,
        );

        $handler->handle($error, $context);

        self::assertSame('existing', $output->fetch());
    }

    public function testZeroContentIsNotMistakenForEmptyOutput(): void
    {
        $output = new BufferedOutput();
        $handler = new RenderingCliErrorHandler(new ErrorRendererStub('0'));
        $handler->handle(
            new ErrorOccurrence(new RuntimeException(), 'ERR-zero'),
            new CliContext(new ArrayInput([]), $output),
        );

        self::assertSame('0' . PHP_EOL, $output->fetch());
    }

    public function testSupportsOnlyCliContext(): void
    {
        $handler = new RenderingCliErrorHandler();

        self::assertTrue($handler->supports(
            new ErrorOccurrence(new RuntimeException(), 'ERR-test'),
            new CliContext(new ArrayInput([]), new BufferedOutput()),
        ));
        self::assertFalse($handler->supports(
            new ErrorOccurrence(new RuntimeException(), 'ERR-test'),
            new HttpContext(new ServerRequest('GET', '/')),
        ));
    }

    public function testRenderReturnsOutputFromRenderer(): void
    {
        $handler = new RenderingCliErrorHandler(new ErrorRendererStub('rendered error'));

        $output = $handler->render(
            new ErrorOccurrence(new RuntimeException(), 'ERR-test'),
            new CliContext(new ArrayInput([]), new BufferedOutput()),
        )->content;

        self::assertSame('rendered error', $output);
    }

    public function testUnsupportedRendererDoesNotInterceptAnotherCliHandler(): void
    {
        $renderer = $this->createMock(\Componenta\Error\Renderer\ErrorRendererInterface::class);
        $renderer->method('supports')->willReturn(false);
        $renderer->expects(self::never())->method('render');
        $delegate = new RenderingCliErrorHandler($renderer);
        $handler = new \Componenta\Error\Handler\CliErrorHandler(new RenderingCliErrorHandler(new ErrorRendererStub('fallback')));
        $handler->addHandler($delegate, priority: 100);
        $output = new BufferedOutput();
        $context = new CliContext(new ArrayInput([]), $output);
        $error = new ErrorOccurrence(new RuntimeException(), 'ERR-test');

        self::assertFalse($delegate->supports($error, $context));
        $handler->handle($error, $context);
        self::assertSame('fallback' . PHP_EOL, $output->fetch());
    }

    public function testDirectRenderRejectsUnsupportedRendererWithoutInvokingIt(): void
    {
        $renderer = $this->createMock(\Componenta\Error\Renderer\ErrorRendererInterface::class);
        $renderer->method('supports')->willReturn(false);
        $renderer->expects(self::never())->method('render');
        $handler = new RenderingCliErrorHandler($renderer);
        $this->expectException(\InvalidArgumentException::class);
        $handler->render(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), CliContext::fromArgv());
    }
}
