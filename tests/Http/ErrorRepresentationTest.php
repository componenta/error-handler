<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Http;

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
            ->generate(new RuntimeException(), new HttpContext(new ServerRequest('GET', '/', ['Accept' => $accept])));

        self::assertSame($contentType . '; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
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
    }

    public function testDeclaredRepresentationWinsOverAcceptAndHeadHasNoBody(): void
    {
        $factory = new Psr17Factory();
        $error = new RuntimeException('failure');
        $html = new HttpContext(new ServerRequest('GET', '/', ['Accept' => 'text/html']));
        $json = new HttpContext(new ServerRequest('GET', '/', ['Accept' => 'application/json']));
        self::assertSame('application/json; charset=utf-8', (new HttpErrorResponseGenerator($factory, new JsonRenderer()))->generate($error, $html)->getHeaderLine('Content-Type'));
        self::assertSame('text/html; charset=utf-8', (new HttpErrorResponseGenerator($factory, new HtmlRenderer()))->generate($error, $json)->getHeaderLine('Content-Type'));
        self::assertSame('text/plain; charset=utf-8', (new HttpErrorResponseGenerator($factory, new ErrorRendererStub('test')))->generate($error, $json)->getHeaderLine('Content-Type'));
        self::assertSame('', (string) (new HttpErrorResponseGenerator($factory))->generate($error, new HttpContext(new ServerRequest('HEAD', '/')))->getBody());
    }
}
