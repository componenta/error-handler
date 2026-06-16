<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\Context\CliContext;
use Componenta\Error\Renderer\HtmlRenderer;
use DOMDocument;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[TestDox('HtmlRenderer')]
final class HtmlRendererTest extends TestCase
{
    public function testRenderReturnsValidHtml(): void
    {
        $renderer = new HtmlRenderer();
        $exception = new RuntimeException('Test error');

        $output = $renderer->render($exception, CliContext::fromArgv());

        $dom = new DOMDocument();
        $result = @$dom->loadHTML($output, LIBXML_NOERROR);

        self::assertTrue($result);
    }
}