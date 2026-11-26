<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Context\CliContext;
use Componenta\Error\Handler\CliErrorHandler;
use Componenta\Error\Handler\RenderingCliErrorHandler;
use Componenta\Error\Renderer\PlainTextRenderer;
use Componenta\Error\Renderer\SafeCliRenderer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class SafeCliRendererTest extends TestCase
{
    public function testDefaultCliHandlerHidesSensitiveErrorDetails(): void
    {
        $output = new BufferedOutput();
        CliErrorHandler::createDefaults()->handle(
            new ErrorOccurrence(new RuntimeException('database credentials leaked'), 'ERR-test'),
            new CliContext(new ArrayInput([]), $output),
        );
        $rendered = $output->fetch();

        self::assertStringContainsString('An unexpected error occurred', $rendered);
        self::assertMatchesRegularExpression('/Error ID: ERR-test/', $rendered);
        self::assertStringNotContainsString('database credentials leaked', $rendered);
        self::assertStringNotContainsString(__FILE__, $rendered);
    }

    public function testSafeCliRendererUsesExistingCorrelationAndNoExceptionDetails(): void
    {
        $context = new CliContext(new ArrayInput([]), new BufferedOutput());
        $renderer = new SafeCliRenderer();
        self::assertSame("An unexpected error occurred\nError ID: ERR-example", $renderer->render(new ErrorOccurrence(new RuntimeException('private'), 'ERR-example'), $context)->content);
    }

    public function testDiagnosticRendererRemainsAnExplicitOptIn(): void
    {
        $output = new BufferedOutput();
        (new RenderingCliErrorHandler(new PlainTextRenderer(debug: true)))->handle(
            new ErrorOccurrence(new RuntimeException('diagnostic message'), 'ERR-test'),
            new CliContext(new ArrayInput([]), $output),
        );

        self::assertStringContainsString('diagnostic message', $output->fetch());
    }
}
