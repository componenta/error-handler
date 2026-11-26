<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\Context\HttpContext;
use Componenta\Error\Renderer\PrettyPageRenderer;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[TestDox('PrettyPageRenderer')]
final class PrettyPageRendererTest extends TestCase
{
    public function testRenderPassesPreparedPayloadToCustomTemplate(): void
    {
        $template = tempnam(sys_get_temp_dir(), 'pretty-renderer-');
        self::assertIsString($template);
        file_put_contents(
            $template,
            'app=<?= $e($applicationName) ?>;exception=<?= $e($exception["message"]) ?>;dump=<?= $e($dump(["key" => "<value>"])) ?>',
        );

        try {
            $renderer = new PrettyPageRenderer(applicationName: 'Test <App>', templatePath: $template);

            $output = $renderer->render(new RuntimeException('Payment <failed>'), $this->context())->content;

            self::assertStringContainsString('app=Test &lt;App&gt;', $output);
            self::assertStringContainsString('exception=Payment &lt;failed&gt;', $output);
            self::assertStringContainsString('key', $output);
            self::assertStringContainsString('&lt;value&gt;', $output);
        } finally {
            @unlink($template);
        }
    }

    public function testRenderIncludesCustomExceptionProperties(): void
    {
        $renderer = new PrettyPageRenderer();
        $exception = new class ('Payment failed') extends RuntimeException {
            public function __construct(
                string $message,
                private readonly string $invoiceId = 'inv-100',
            ) {
                parent::__construct($message);
            }
        };

        $output = $renderer->render($exception, $this->context())->content;

        self::assertStringContainsString('invoiceId', $output);
        self::assertStringContainsString('inv-100', $output);
    }

    public function testDebugPageDisplaysCorrelationAndDisablesReferrers(): void
    {
        $context = $this->context()->withAttribute(
            \Componenta\Error\Context\ErrorContextAttribute::ERROR_ID,
            'ERR-correlation-123',
        );
        $html = (new PrettyPageRenderer(includeSource: false))->render(new RuntimeException('failure'), $context)->content;

        self::assertStringContainsString('Error ID: ERR-correlation-123', $html);
        self::assertStringContainsString('<meta name="referrer" content="no-referrer">', $html);
    }

    private function context(): HttpContext
    {
        return new HttpContext(new ServerRequest('GET', '/'));
    }
}
