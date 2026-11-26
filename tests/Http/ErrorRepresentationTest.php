<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Http;

use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\Http\HttpErrorResponseGenerator;
use Componenta\Error\Renderer\HtmlRenderer;
use Componenta\Error\Renderer\JsonRenderer;
use Componenta\Error\Renderer\SafeRenderer;
use Componenta\Error\Tests\Fixture\ErrorRendererStub;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorRepresentationTest extends TestCase
{
    #[DataProvider('acceptHeaders')]
    public function testNegotiation(string $accept, string $contentType): void
    {
        $response = (new HttpErrorResponseGenerator(new Psr17Factory(), new SafeRenderer()))
            ->generate(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), new HttpContext(new ServerRequest('GET', '/', ['Accept' => $accept])));

        self::assertSame($contentType . '; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        self::assertSame('Accept', $response->getHeaderLine('Vary'));
        if ($contentType === 'application/json') {
            self::assertIsArray(json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
        }
    }

    public static function acceptHeaders(): iterable
    {
        yield ['APPLICATION/JSON', 'application/json'];
        yield ['application/json;q=0, text/html', 'text/html'];
        yield ['text/html;q=0.4, application/json;q=0.9', 'application/json'];
        yield ['application/json;q=0, */*;q=1', 'text/html'];
        yield ['text/html;q=0, application/*;q=0.5', 'application/json'];
        yield ['application/json-foo', 'text/html'];
        yield ['*/*', 'text/html'];
        yield ['application/json;charset=utf-8;q=0, application/json;q=1, text/html;q=0.5', 'text/html'];
        yield ['application/json;q=0, application/json;charset="UTF-8";q=0.9, text/html;q=0.5', 'application/json'];
        yield ['text/html;profile="a,b;c", application/json', 'application/json'];
        yield ['application/json;profile="unterminated', 'text/html'];
        yield ['application/json;charset=utf-8;q=0, application/json;charset=utf-8;q=0.9, text/html;q=0.5', 'application/json'];
        yield ['application/json;charset=utf-8;q=0.9, application/json;charset=utf-8;q=0, text/html;q=0.5', 'application/json'];
    }

    public function testDeclaredRepresentationWinsOverAcceptAndHeadHasNoBody(): void
    {
        $factory = new Psr17Factory();
        $error = new RuntimeException('failure');
        $html = new HttpContext(new ServerRequest('GET', '/', ['Accept' => 'text/html']));
        $json = new HttpContext(new ServerRequest('GET', '/', ['Accept' => 'application/json']));
        self::assertSame('application/json; charset=utf-8', (new HttpErrorResponseGenerator($factory, new JsonRenderer()))->generate(new ErrorOccurrence($error, 'ERR-test'), $html)->getHeaderLine('Content-Type'));
        self::assertSame('text/html; charset=utf-8', (new HttpErrorResponseGenerator($factory, new HtmlRenderer()))->generate(new ErrorOccurrence($error, 'ERR-test'), $json)->getHeaderLine('Content-Type'));
        self::assertSame('text/plain; charset=utf-8', (new HttpErrorResponseGenerator($factory, new ErrorRendererStub('test')))->generate(new ErrorOccurrence($error, 'ERR-test'), $json)->getHeaderLine('Content-Type'));
        self::assertSame('', (string) (new HttpErrorResponseGenerator($factory))->generate(new ErrorOccurrence($error, 'ERR-test'), new HttpContext(new ServerRequest('HEAD', '/')))->getBody());
    }
}
