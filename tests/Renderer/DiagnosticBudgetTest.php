<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\Diagnostic\DiagnosticData;
use Componenta\Error\Renderer\PrettyPageRenderer;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class DiagnosticBudgetTest extends TestCase
{
    public function testSharedNestedArraysHaveAGlobalBudget(): void
    {
        $value = str_repeat('a', 512);
        for ($depth = 0; $depth < 4; ++$depth) {
            $value = array_fill(0, 10, $value);
        }

        $result = DiagnosticData::sanitize(['password' => 'secret-marker', 'data' => $value]);
        self::assertIsArray($result);
        self::assertSame('[redacted]', $result['password']);
        self::assertLessThan(300_000, strlen(json_encode($result, JSON_THROW_ON_ERROR)));
    }

    public function testPrettyRendererSharesDataBudgetAndResetsItForEachRender(): void
    {
        $data = array_fill(0, 100, str_repeat('a', 4096));
        $request = (new ServerRequest('POST', '/'))->withQueryParams($data)->withParsedBody($data);
        $template = tempnam(sys_get_temp_dir(), 'error-budget-');
        self::assertIsString($template);
        file_put_contents($template, '<?php echo json_encode([$getData, $postData], JSON_THROW_ON_ERROR); ?>');
        try {
            $renderer = new PrettyPageRenderer(templatePath: $template, includeSource: false);
            $error = new ErrorOccurrence(new RuntimeException('test'), 'ERR-budget');
            $context = new HttpContext($request);
            $first = $renderer->render($error, $context)->content;
            self::assertLessThan(300_000, strlen($first));
            self::assertSame($first, $renderer->render($error, $context)->content);
        } finally {
            unlink($template);
        }
    }

    public function testPrettyRendererCapsChainAndMessagesEvenWithUnlimitedConfiguration(): void
    {
        $previous = null;
        for ($i = 0; $i < 40; ++$i) {
            $previous = new RuntimeException(str_repeat('x', 50_000), 0, $previous);
        }
        $template = tempnam(sys_get_temp_dir(), 'error-chain-');
        self::assertIsString($template);
        file_put_contents($template, '<?php echo json_encode([count($exceptionChain), count($frames), strlen($exception["message"])]); ?>');
        try {
            $renderer = new PrettyPageRenderer(templatePath: $template, maxFrames: 1, maxExceptions: null, includeSource: false);
            $result = $renderer->render(new ErrorOccurrence($previous, 'ERR-chain'), new HttpContext(new ServerRequest('GET', '/')));
            self::assertSame([32, 1, 16_384], json_decode($result->content, true, flags: JSON_THROW_ON_ERROR));
        } finally {
            unlink($template);
        }
    }

    public function testDiagnosticJsonBoundsTraceAndMessage(): void
    {
        $exception = $this->deepException(140);
        self::assertGreaterThan(100, count($exception->getTrace()));
        $renderer = new \Componenta\Error\Renderer\JsonRenderer(debug: true);
        $result = $renderer->render(new ErrorOccurrence($exception, 'ERR-json'), new HttpContext(new ServerRequest('GET', '/')));
        $error = json_decode($result->content, true, flags: JSON_THROW_ON_ERROR)['error'];
        self::assertCount(100, $error['trace']);
        self::assertSame(count($exception->getTrace()) - 100, $error['trace_omitted']);
        self::assertLessThanOrEqual(16_384, strlen($error['message']));
        self::assertStringEndsWith('...', $error['message']);
    }

    public function testLongMessagesAreBoundedBeforeHtmlAndTextFormatting(): void
    {
        $error = new ErrorOccurrence(new RuntimeException(str_repeat('x', 100_000) . 'tail-marker'), 'ERR-long');
        $context = new HttpContext(new ServerRequest('GET', '/'));
        foreach ([new \Componenta\Error\Renderer\HtmlRenderer(), new \Componenta\Error\Renderer\PlainTextRenderer()] as $renderer) {
            $content = $renderer->render($error, $context)->content;
            self::assertLessThan(25_000, strlen($content));
            self::assertStringNotContainsString('tail-marker', $content);
            self::assertStringContainsString(str_repeat('x', 100), $content);
        }
    }

    private function deepException(int $depth): RuntimeException
    {
        return $depth > 0 ? $this->deepException($depth - 1) : new RuntimeException(str_repeat('x', 50_000));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('eofModes')]
    public function testChunkedDiagnosticReadRestoresPositionAndRedactsSecrets(bool $delayedEof): void
    {
        $payload = '{"password":"redact-me","name":"visible-value"}';
        $position = 5;
        $readPastEnd = false;
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('tell')->willReturnCallback(static function () use (&$position): int {
            return $position;
        });
        $stream->method('rewind')->willReturnCallback(static function () use (&$position): void {
            $position = 0;
        });
        $stream->method('seek')->willReturnCallback(static function (int $offset) use (&$position): void {
            $position = $offset;
        });
        $stream->method('read')->willReturnCallback(static function (int $length) use (&$position, &$readPastEnd, $payload): string {
            $chunk = substr($payload, $position, min($length, 5));
            if ($chunk === '') {
                $readPastEnd = true;
            }
            $position += strlen($chunk);
            return $chunk;
        });
        $stream->method('eof')->willReturnCallback(static function () use (&$position, &$readPastEnd, $payload, $delayedEof): bool {
            return $delayedEof ? $readPastEnd : $position >= strlen($payload);
        });

        $request = (new ServerRequest('POST', '/', ['Content-Type' => 'application/json']))->withBody($stream);
        $template = tempnam(sys_get_temp_dir(), 'error-body-');
        self::assertIsString($template);
        file_put_contents($template, '<?php echo $rawBody["content"] ?? ""; ?>');

        try {
            $body = (new PrettyPageRenderer(templatePath: $template, captureJsonBody: true, includeSource: false))
                ->render(new ErrorOccurrence(new RuntimeException('original'), 'ERR-test'), new HttpContext($request))->content;

            self::assertSame(['password' => '[redacted]', 'name' => 'visible-value'], json_decode($body, true, flags: JSON_THROW_ON_ERROR));
            self::assertSame(5, $position);
        } finally {
            unlink($template);
        }
    }

    public static function eofModes(): iterable
    {
        yield 'EOF at end' => [false];
        yield 'EOF after empty read' => [true];
    }
}
