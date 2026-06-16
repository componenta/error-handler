<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Handler;

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
            new RuntimeException(),
            new CliContext(new ArrayInput([]), $output),
        );

        self::assertStringContainsString('error output', $output->fetch());
    }

    public function testSupportsOnlyCliContext(): void
    {
        $handler = new RenderingCliErrorHandler();

        self::assertTrue($handler->supports(
            new RuntimeException(),
            new CliContext(new ArrayInput([]), new BufferedOutput()),
        ));
        self::assertFalse($handler->supports(
            new RuntimeException(),
            new HttpContext(new ServerRequest('GET', '/')),
        ));
    }

    public function testRenderReturnsOutputFromRenderer(): void
    {
        $handler = new RenderingCliErrorHandler(new ErrorRendererStub('rendered error'));

        $output = $handler->render(
            new RuntimeException(),
            new CliContext(new ArrayInput([]), new BufferedOutput()),
        );

        self::assertSame('rendered error', $output);
    }
}
