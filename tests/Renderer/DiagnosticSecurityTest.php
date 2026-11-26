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
        $secret = static fn (string $kind): string => $kind . '-secret-' . '123';
        $request = (new ServerRequest('POST', 'https://example.test/?token=' . $secret('query'), [
            'Authorization' => 'Bearer ' . $secret('header'),
            'Cookie' => 'session=' . $secret('cookie'),
        ], $secret('raw')))->withQueryParams(['token' => $secret('query')])
            ->withCookieParams(['session' => $secret('cookie')])
            ->withParsedBody(['nested' => ['password' => $secret('body')]]);
        $error = new class extends RuntimeException {
            private string $accessToken = 'property-' . 'secret-123';
        };

        $html = (new PrettyPageRenderer(includeSource: false))->render($error, new HttpContext($request));

        foreach ([$secret('query'), $secret('header'), $secret('cookie'), $secret('body'), $secret('raw'), $secret('property'), 'cdn.' . 'jsdelivr.net', 'fonts.' . 'googleapis.com'] as $secret) {
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
