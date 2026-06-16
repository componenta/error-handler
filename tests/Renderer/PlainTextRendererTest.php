<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\Context\CliContext;
use Componenta\Error\Renderer\PlainTextRenderer;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[TestDox('PlainTextRenderer')]
final class PlainTextRendererTest extends TestCase
{
    public function testRenderReturnsNonEmptyString(): void
    {
        $renderer = new PlainTextRenderer();
        $exception = new RuntimeException('Test error');

        $output = $renderer->render($exception, CliContext::fromArgv());

        self::assertNotEmpty($output);
    }
}