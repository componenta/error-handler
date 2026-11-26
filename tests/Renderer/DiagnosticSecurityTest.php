<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\Context\HttpContext;
use Componenta\Error\Renderer\PrettyPageRenderer;
use Componenta\Error\Renderer\ConsoleErrorRenderer;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DiagnosticSecurityTest extends TestCase
{
    public function testSecretsAndExternalResourcesAreNotRendered(): void
    {
        $request = (new ServerRequest('POST', 'https://example.test/?token=query-secret-123', [
            'Authorization' => 'Bearer header-secret-123',
            'Cookie' => 'session=cookie-secret-123',
        ], 'raw-secret-123'))->withQueryParams(['token' => 'query-secret-123'])
            ->withCookieParams(['session' => 'cookie-secret-123'])
            ->withParsedBody(['nested' => ['password' => 'body-secret-123']]);
        $error = new class extends RuntimeException {
            private string $accessToken = 'property-secret-123';
        };

        $html = (new PrettyPageRenderer())->render($error, new HttpContext($request));

        foreach (['query-secret-123', 'header-secret-123', 'cookie-secret-123', 'body-secret-123', 'raw-secret-123', 'property-secret-123', 'cdn.jsdelivr.net', 'fonts.googleapis.com'] as $secret) {
            self::assertStringNotContainsString($secret, $html);
        }
        self::assertStringContainsString('[redacted]', $html);
    }

    public function testProductionPrettyRendererDoesNotBuildDiagnostics(): void
    {
        $html = (new PrettyPageRenderer(debug: false))->render(
            new RuntimeException('private-message-123'), new HttpContext(new ServerRequest('GET', '/')),
        );
        self::assertStringNotContainsString('private-message-123', $html);
    }

    public function testConsoleDoesNotEmitUntrustedEscapeSequences(): void
    {
        $renderer = new ConsoleErrorRenderer(debug: false, colors: false, terminalWidth: 20);
        $text = $renderer->render(new RuntimeException("bad\x1b[2Jmessage"));
        self::assertStringNotContainsString("\x1b", $text);
        self::assertStringContainsString('bad', $text);
    }
}
