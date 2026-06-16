<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\Context\CliContext;
use Componenta\Error\Context\ErrorContextAttribute;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\Renderer\SafeRenderer;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[TestDox('SafeRenderer')]
final class SafeRendererTest extends TestCase
{
    public function testRenderReturnsSafeHtmlWithoutExposingExceptionDetails(): void
    {
        $renderer = new SafeRenderer();
        $exception = new RuntimeException('database password leaked');

        $output = $renderer->render($exception, CliContext::fromArgv());

        self::assertStringContainsString('An unexpected error occurred', $output);
        self::assertStringContainsString('Error ID: ERR-', $output);
        self::assertStringNotContainsString('database password leaked', $output);
    }

    public function testRenderUsesCustomHtmlTemplate(): void
    {
        $template = tempnam(sys_get_temp_dir(), 'safe-renderer-');
        self::assertIsString($template);
        file_put_contents(
            $template,
            'status=<?= $e($statusCode) ?>;message=<?= $e($message) ?>;support=<?= $e($supportEmail) ?>',
        );

        try {
            $renderer = new SafeRenderer(
                defaultMessage: 'Hidden <safe>',
                supportEmail: 'support@example.test',
                templatePath: $template,
            );

            $output = $renderer->render(new RuntimeException('database password leaked'), CliContext::fromArgv());

            self::assertSame(
                'status=500;message=Hidden &lt;safe&gt;;support=support@example.test',
                $output,
            );
        } finally {
            @unlink($template);
        }
    }

    public function testRenderReturnsSafeJsonForJsonRequests(): void
    {
        $renderer = new SafeRenderer(defaultMessage: 'Internal Server Error');
        $context = (new HttpContext(new ServerRequest('GET', '/broken', ['Accept' => 'application/json'])))
            ->withAttribute(ErrorContextAttribute::HTTP_STATUS_CODE, 503);

        $output = $renderer->render(new RuntimeException('database password leaked'), $context);
        $decoded = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        self::assertMatchesRegularExpression('/^ERR-[A-F0-9]{8}-\d+$/', $decoded['error']['id']);
        unset($decoded['error']['id']);
        self::assertSame(
            [
                'error' => [
                    'status' => 503,
                    'message' => 'Internal Server Error',
                ],
            ],
            $decoded,
        );
        self::assertStringNotContainsString('database password leaked', $output);
    }
}
