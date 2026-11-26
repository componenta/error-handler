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

    public function testChunkedDiagnosticReadRestoresPositionAndRedactsSecrets(): void
    {
        $payload = '{"password":"redact-me","name":"visible-value"}';
        $position = 5;
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
        $stream->method('read')->willReturnCallback(static function (int $length) use (&$position, $payload): string {
            $chunk = substr($payload, $position, min($length, 5));
            $position += strlen($chunk);
            return $chunk;
        });
        $stream->method('eof')->willReturnCallback(static function () use (&$position, $payload): bool {
            return $position >= strlen($payload);
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
}
