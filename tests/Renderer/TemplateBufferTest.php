<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\Context\HttpContext;
use Componenta\Error\Renderer\PrettyPageRenderer;
use Componenta\Error\Renderer\SafeRenderer;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TemplateBufferTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function brokenTemplates(): iterable
    {
        yield 'unbalanced success' => ['outer<?php ob_start(); echo "nested"; ?>'];
        yield 'exception in nested buffer' => ['outer<?php ob_start(); echo "nested"; throw new RuntimeException("template failed"); ?>'];
    }

    #[DataProvider('brokenTemplates')]
    public function testSafeRendererFallsBackWithoutLeakingTemplateOutput(string $source): void
    {
        $template = tempnam(sys_get_temp_dir(), 'error-template-');
        self::assertIsString($template);
        file_put_contents($template, $source);
        $level = ob_get_level();
        ob_start();

        try {
            echo 'parent-buffer';
            $html = (new SafeRenderer(templatePath: $template))->render(
                new RuntimeException('original'),
                new HttpContext(new ServerRequest('GET', '/')),
            );
            self::assertSame($level + 1, ob_get_level());
            self::assertSame('parent-buffer', ob_get_contents());
            self::assertStringContainsString('An unexpected error occurred', $html);
            self::assertStringNotContainsString('nested', $html);
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            unlink($template);
        }
    }

    #[DataProvider('brokenTemplates')]
    public function testPrettyRendererRestoresBuffersBeforePropagatingFailure(string $source): void
    {
        $template = tempnam(sys_get_temp_dir(), 'error-template-');
        self::assertIsString($template);
        file_put_contents($template, $source);
        $level = ob_get_level();

        try {
            $this->expectException(RuntimeException::class);
            (new PrettyPageRenderer(templatePath: $template, includeSource: false))->render(
                new RuntimeException('original'),
                new HttpContext(new ServerRequest('GET', '/')),
            );
        } finally {
            try {
                self::assertSame($level, ob_get_level());
            } finally {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
                unlink($template);
            }
        }
    }
}
