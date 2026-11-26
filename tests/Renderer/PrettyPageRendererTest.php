<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\ErrorOccurrence;
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

            $output = $renderer->render(new ErrorOccurrence(new RuntimeException('Payment <failed>'), 'ERR-test'), $this->context())->content;

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

        $output = $renderer->render(new ErrorOccurrence($exception, 'ERR-test'), $this->context())->content;

        self::assertStringContainsString('invoiceId', $output);
        self::assertStringContainsString('inv-100', $output);
    }

    public function testDebugPageDisplaysCorrelationAndDisablesReferrers(): void
    {
        $context = $this->context();
        $html = (new PrettyPageRenderer(includeSource: false))->render(new ErrorOccurrence(new RuntimeException('failure'), 'ERR-correlation-123'), $context)->content;

        self::assertStringContainsString('Error ID: ERR-correlation-123', $html);
        self::assertStringContainsString('<meta name="referrer" content="no-referrer">', $html);
    }

    private function context(): HttpContext
    {
        return new HttpContext(new ServerRequest('GET', '/'));
    }

    public function testPathShorteningDoesNotReuseRelativeResolutionAfterCwdChanges(): void
    {
        $cwd = getcwd();
        self::assertIsString($cwd);
        $root = sys_get_temp_dir() . '/error-path-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        mkdir($root . '/other', 0700);
        file_put_contents($root . '/source.php', '<?php');
        file_put_contents($root . '/other/source.php', '<?php');
        file_put_contents($root . '/template.phtml', '<?= $e($exception["shortFile"]) ?>');
        try {
            chdir($root);
            $renderer = new PrettyPageRenderer(templatePath: $root . '/template.phtml', includeSource: false);
            $cold = new PrettyPageRenderer(templatePath: $root . '/template.phtml', includeSource: false);
            $exception = new class extends RuntimeException {
                public function __construct() { parent::__construct('test'); $this->file = 'source.php'; }
            };
            $error = new ErrorOccurrence($exception, 'ERR-test');
            self::assertSame('./source.php', $renderer->render($error, $this->context())->content);
            chdir($root . '/other');
            self::assertSame('./other/source.php', $cold->render($error, $this->context())->content);
            self::assertSame($cold->render($error, $this->context())->content, $renderer->render($error, $this->context())->content);
        } finally {
            chdir($cwd);
            unlink($root . '/source.php');
            unlink($root . '/other/source.php');
            unlink($root . '/template.phtml');
            rmdir($root . '/other');
            rmdir($root);
        }
    }
}
