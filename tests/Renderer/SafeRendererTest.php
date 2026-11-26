<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\ErrorOccurrence;
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

        $output = $renderer->render(new ErrorOccurrence($exception, 'ERR-test'), CliContext::fromArgv())->content;

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

            $output = $renderer->render(new ErrorOccurrence(new RuntimeException('database password leaked'), 'ERR-test'), CliContext::fromArgv())->content;

            self::assertSame(
                'status=500;message=Hidden &lt;safe&gt;;support=support@example.test',
                $output,
            );
        } finally {
            @unlink($template);
        }
    }

    public function testTemplateMetadataFailureUsesIndependentSafeFallback(): void
    {
        $scheme = 'errorhandlerstat' . bin2hex(random_bytes(8));
        self::assertTrue(stream_wrapper_register($scheme, FailingTemplateStatWrapper::class));
        try {
            $renderer = new SafeRenderer(defaultMessage: 'Safe <message>', templatePath: $scheme . '://template');
            $result = $renderer->render(
                new ErrorOccurrence(new RuntimeException('sensitive-marker'), 'ERR-stat-failure'),
                new HttpContext(new ServerRequest('GET', '/')),
            );
            self::assertSame('text/html; charset=utf-8', $result->contentType);
            self::assertStringContainsString('Safe &lt;message&gt;', $result->content);
            self::assertStringContainsString('ERR-stat-failure', $result->content);
            self::assertStringNotContainsString('sensitive-marker', $result->content);
        } finally {
            stream_wrapper_unregister($scheme);
        }
    }

    public function testRenderReturnsSafeJsonForJsonRequests(): void
    {
        $renderer = new SafeRenderer(defaultMessage: 'Internal Server Error');
        $context = (new HttpContext(new ServerRequest('GET', '/broken', ['Accept' => 'application/json'])))
            ->withAttribute(ErrorContextAttribute::HTTP_STATUS_CODE, 503);

        $output = $renderer->render(new ErrorOccurrence(new RuntimeException('database password leaked'), 'ERR-test'), $context)->content;
        $decoded = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        self::assertMatchesRegularExpression('/^ERR-test$/', $decoded['error']['id']);
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

/** Test-only stream wrapper that fails during template metadata lookup. */
final class FailingTemplateStatWrapper
{
    public mixed $context;

    public function url_stat(string $path, int $flags): array|false
    {
        throw new RuntimeException('Template metadata unavailable');
    }
}
