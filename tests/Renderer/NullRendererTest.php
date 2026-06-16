<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\Context\CliContext;
use Componenta\Error\Renderer\NullRenderer;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[TestDox('NullRenderer')]
final class NullRendererTest extends TestCase
{
    public function testRenderReturnsEmptyString(): void
    {
        $renderer = new NullRenderer();
        $exception = new RuntimeException('Test error');

        $output = $renderer->render($exception, CliContext::fromArgv());

        self::assertEmpty($output);
    }
}