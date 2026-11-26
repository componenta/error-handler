<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Context\CliContext;
use Componenta\Error\Renderer\JsonRenderer;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[TestDox('JsonRenderer')]
final class JsonRendererTest extends TestCase
{
    public function testRenderReturnsValidJson(): void
    {
        $renderer = new JsonRenderer();
        $exception = new RuntimeException('Test error', 500);

        $output = $renderer->render(new ErrorOccurrence($exception, 'ERR-test'), CliContext::fromArgv())->content;

        self::assertJson($output);
    }
}