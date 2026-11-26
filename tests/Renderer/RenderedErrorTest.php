<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\Context\CliContext;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Handler\RenderingCliErrorHandler;
use Componenta\Error\Http\HttpErrorResponseGenerator;
use Componenta\Error\Renderer\CompositeErrorRenderer;
use Componenta\Error\Renderer\ConsoleErrorRenderer;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Renderer\HtmlRenderer;
use Componenta\Error\Renderer\JsonRenderer;
use Componenta\Error\Renderer\NullRenderer;
use Componenta\Error\Renderer\PlainTextRenderer;
use Componenta\Error\Renderer\PrettyPageRenderer;
use Componenta\Error\Renderer\RenderedError;
use Componenta\Error\Renderer\SafeCliRenderer;
use Componenta\Error\Renderer\SafeRenderer;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stringable;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

final class RenderedErrorTest extends TestCase
{
    public function testResultExposesContentAndTypeAndSupportsExplicitStringConversion(): void
    {
        $result = new RenderedError(content: '{"error":"failed"}', contentType: 'application/problem+json');

        self::assertInstanceOf(Stringable::class, $result);
        self::assertSame('{"error":"failed"}', $result->content);
        self::assertSame('application/problem+json', $result->contentType);
        self::assertSame($result->content, (string) $result);
        self::assertSame('', (string) new RenderedError('', 'text/plain'));
    }

    #[DataProvider('invalidContentTypes')]
    public function testRejectsMissingAndControlCharacterContentTypes(string $contentType): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RenderedError('content', $contentType);
    }

    public static function invalidContentTypes(): iterable
    {
        yield [''];
        yield ['  '];
        yield ["text/plain\r\nX-Injected: yes"];
        yield ["text/plain\0"];
    }

    #[DataProvider('renderers')]
    public function testEveryRendererDeclaresActualMediaType(ErrorRendererInterface $renderer, ErrorContextInterface $context, string $type): void
    {
        $result = $renderer->render(new RuntimeException('failure'), $context);

        self::assertInstanceOf(RenderedError::class, $result);
        self::assertSame($type, $result->contentType);
        self::assertSame($result->content, (string) $result);
        if ($renderer instanceof NullRenderer) {
            self::assertSame('', $result->content);
        } else {
            self::assertNotSame('', $result->content);
        }
    }

    public static function renderers(): iterable
    {
        $html = new HttpContext(new ServerRequest('GET', '/', ['Accept' => 'text/html']));
        $json = new HttpContext(new ServerRequest('GET', '/', ['Accept' => 'application/json']));
        $cli = new CliContext(new ArrayInput([]), new BufferedOutput());
        yield 'html' => [new HtmlRenderer(), $json, 'text/html; charset=utf-8'];
        yield 'json' => [new JsonRenderer(), $html, 'application/json; charset=utf-8'];
        yield 'plain text' => [new PlainTextRenderer(), $cli, 'text/plain; charset=utf-8'];
        yield 'console' => [new ConsoleErrorRenderer(colors: false, contextLines: 0), $cli, 'text/plain; charset=utf-8'];
        yield 'null' => [new NullRenderer(), $cli, 'text/plain; charset=utf-8'];
        yield 'safe CLI' => [new SafeCliRenderer(), $cli, 'text/plain; charset=utf-8'];
        yield 'safe html' => [new SafeRenderer(), $html, 'text/html; charset=utf-8'];
        yield 'safe json' => [new SafeRenderer(), $json, 'application/json; charset=utf-8'];
        yield 'pretty html' => [new PrettyPageRenderer(includeSource: false), $json, 'text/html; charset=utf-8'];
        yield 'pretty non-debug' => [new PrettyPageRenderer(debug: false), $json, 'application/json; charset=utf-8'];
    }

    public function testCompositeAndTransportConsumersPreserveCustomRendererResult(): void
    {
        $result = new RenderedError('{"error":"custom"}', 'application/problem+json');
        $renderer = new readonly class($result) implements ErrorRendererInterface {
            public function __construct(private RenderedError $result) {}
            public function supports(Throwable $exception, ErrorContextInterface $context): bool { return true; }
            public function render(Throwable $exception, ErrorContextInterface $context): RenderedError { return $this->result; }
        };
        $composite = new CompositeErrorRenderer();
        $composite->addRenderer($renderer);
        $error = new RuntimeException('failure');
        $http = new HttpContext(new ServerRequest('GET', '/', ['Accept' => 'text/html']));
        self::assertSame($result, $composite->render($error, $http));

        $response = (new HttpErrorResponseGenerator(new Psr17Factory(), $composite))->generate($error, $http);
        self::assertSame($result->contentType, $response->getHeaderLine('Content-Type'));
        self::assertSame($result->content, (string) $response->getBody());

        $output = new BufferedOutput();
        $cli = new CliContext(new ArrayInput([]), $output);
        $handler = new RenderingCliErrorHandler($composite);
        self::assertSame($result, $handler->render($error, $cli));
        $handler->handle($error, $cli);
        self::assertSame($result->content . PHP_EOL, $output->fetch());
    }
}
