<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\ErrorOccurrence;
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

        $html = (new PrettyPageRenderer(includeSource: false))->render(new ErrorOccurrence($error, 'ERR-test'), new HttpContext($request))->content;

        foreach ([$secret('query'), $secret('header'), $secret('cookie'), $secret('body'), $secret('raw'), $secret('property'), 'cdn.' . 'jsdelivr.net', 'fonts.' . 'googleapis.com'] as $secret) {
            self::assertStringNotContainsString($secret, $html);
        }
        self::assertStringContainsString('[redacted]', $html);
    }

    public function testDiagnosticRenderersDoNotInvokeHooksOnInvalidExceptionCode(): void
    {
        $code = new class implements \JsonSerializable, \Stringable {
            public function jsonSerialize(): mixed { throw new RuntimeException('unexpected serialization'); }
            public function __toString(): string { throw new RuntimeException('unexpected string conversion'); }
        };
        $exception = new class ($code) extends RuntimeException {
            public function __construct(mixed $code) {
                parent::__construct('diagnostic-marker');
                $this->code = $code;
            }
        };
        $error = new ErrorOccurrence($exception, 'ERR-invalid-code');
        $http = new HttpContext(new ServerRequest('GET', '/'));
        $cli = \Componenta\Error\Context\CliContext::fromArgv();

        foreach ([
            [new \Componenta\Error\Renderer\JsonRenderer(debug: true), $http],
            [new \Componenta\Error\Renderer\HtmlRenderer(debug: true), $http],
            [new PrettyPageRenderer(includeSource: false), $http],
            [new ConsoleErrorRenderer(debug: false, colors: false), $cli],
        ] as [$renderer, $context]) {
            $result = $renderer->render($error, $context);
            self::assertStringContainsString('diagnostic-marker', $result->content);
            self::assertStringContainsString('[invalid exception code]', $result->content);
        }
        self::assertSame($code, $exception->getCode());
    }

    public function testJsonRetainsConventionalIntegerAndStringExceptionCodes(): void
    {
        $renderer = new \Componenta\Error\Renderer\JsonRenderer();
        $context = new HttpContext(new ServerRequest('GET', '/'));
        $integer = new RuntimeException('integer', 42);
        $string = new class extends RuntimeException {
            public function __construct() {
                parent::__construct('string');
                $this->code = 'SQLSTATE-123';
            }
        };
        foreach ([$integer, $string] as $exception) {
            $rendered = $renderer->render(new ErrorOccurrence($exception, 'ERR-code'), $context);
            $data = json_decode($rendered->content, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($exception->getCode(), $data['error']['code']);
        }
    }

    public function testProductionPrettyRendererDoesNotBuildDiagnostics(): void
    {
        $html = (new PrettyPageRenderer(debug: false))->render(
            new ErrorOccurrence(new RuntimeException('private-message-123'), 'ERR-test'), new HttpContext(new ServerRequest('GET', '/')),
        )->content;
        self::assertStringNotContainsString('private-message-123', $html);
    }

    public function testConsoleDoesNotEmitUntrustedEscapeSequences(): void
    {
        $renderer = new ConsoleErrorRenderer(debug: false, colors: false, terminalWidth: 20);
        $text = $renderer->render(new ErrorOccurrence(new RuntimeException("bad\x1b[2Jmessage"), 'ERR-test'), \Componenta\Error\Context\CliContext::fromArgv())->content;
        self::assertStringNotContainsString("\x1b", $text);
        self::assertStringContainsString('bad', $text);
    }
}
