<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Context\CliContext;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\Diagnostic\DiagnosticData;
use Componenta\Error\Renderer\ConsoleErrorRenderer;
use Componenta\Error\Renderer\HtmlRenderer;
use Componenta\Error\Renderer\PlainTextRenderer;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class TerminalAndTraceSecurityTest extends TestCase
{
    public function testTerminalSanitizerHandlesRawC1InvalidUtf8AndBidiControls(): void
    {
        $value = "safe \x1b[2J \x9b31m \u{009d} \u{202e} \u{2066} \u{0416}\n";
        $result = DiagnosticData::terminal($value);

        self::assertTrue(mb_check_encoding($result, 'UTF-8'));
        self::assertDoesNotMatchRegularExpression('/[\x{001b}\x{0080}-\x{009f}\x{202e}\x{2066}]/u', $result);
        self::assertStringContainsString("\u{0416}\n", $result);
    }

    public function testConsoleWrapDoesNotSplitMultibyteCharacters(): void
    {
        $message = str_repeat("\u{0416}\u{044f}\u{1f642}", 30);
        $renderer = new ConsoleErrorRenderer(debug: false, colors: false, terminalWidth: 25);
        $result = $renderer->render(new ErrorOccurrence(new RuntimeException($message), 'ERR-test'), \Componenta\Error\Context\CliContext::fromArgv())->content;

        self::assertTrue(mb_check_encoding($result, 'UTF-8'));
        self::assertSame(30, substr_count($result, "\u{1f642}"));
    }

    public function testDiagnosticTraceDoesNotFormatArgumentValues(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        try {
            ini_set('zend.exception_ignore_args', '0');
            $secret = bin2hex(random_bytes(16));
            $error = $this->capture($secret);
            self::assertSame($secret, $error->getTrace()[0]['args'][0]);

            $cli = new CliContext(new ArrayInput([]), new BufferedOutput());
            $http = new HttpContext(new ServerRequest('GET', '/'));
            $plain = (new PlainTextRenderer(debug: true))->render(new ErrorOccurrence($error, 'ERR-test'), $cli)->content;
            $html = (new HtmlRenderer(debug: true))->render(new ErrorOccurrence($error, 'ERR-test'), $http)->content;

            self::assertStringContainsString('capture()', $plain);
            self::assertStringNotContainsString($secret, $plain);
            self::assertStringNotContainsString($secret, $html);
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    private function capture(string $secret): RuntimeException
    {
        return new RuntimeException('diagnostic failure');
    }
}
