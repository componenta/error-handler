<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Http;

use Componenta\Error\Context\CliContext;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\Http\HttpErrorResponseGenerator;
use Componenta\Error\Renderer\SafeRenderer;
use Componenta\Error\Tests\Fixture\ErrorRendererStub;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[TestDox('HttpErrorResponseGenerator')]
final class HttpErrorResponseGeneratorTest extends TestCase
{
    public function testGenerateReturnsResponseWithBodyFromRenderer(): void
    {
        $generator = new HttpErrorResponseGenerator(
            new Psr17Factory(),
            new ErrorRendererStub('error output'),
        );

        $response = $generator->generate(
            new RuntimeException(),
            new HttpContext(new ServerRequest('GET', '/')),
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('error output', (string) $response->getBody());
    }

    public function testGenerateUsesHttpExceptionCodeWhenItIsValid(): void
    {
        $generator = new HttpErrorResponseGenerator(
            new Psr17Factory(),
            new ErrorRendererStub('not found'),
        );

        $response = $generator->generate(
            new RuntimeException('missing', 404),
            new HttpContext(new ServerRequest('GET', '/')),
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGeneratePassesResolvedStatusToSafeRenderer(): void
    {
        $generator = new HttpErrorResponseGenerator(
            new Psr17Factory(),
            new SafeRenderer(defaultMessage: 'Internal Server Error'),
        );

        $response = $generator->generate(
            new RuntimeException('missing', 404),
            new HttpContext(new ServerRequest('GET', '/missing', ['Accept' => 'application/json'])),
        );

        $decoded = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(404, $decoded['error']['status']);
    }

    public function testGenerateSetsJsonContentTypeWhenRequestAcceptsJson(): void
    {
        $generator = new HttpErrorResponseGenerator(
            new Psr17Factory(),
            new ErrorRendererStub('{"message":"error"}'),
        );

        $response = $generator->generate(
            new RuntimeException(),
            new HttpContext(new ServerRequest('GET', '/', ['Accept' => 'application/json'])),
        );

        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    public function testSupportsOnlyHttpContext(): void
    {
        $generator = new HttpErrorResponseGenerator(
            new Psr17Factory(),
            new ErrorRendererStub(),
        );

        self::assertTrue($generator->supports(
            new RuntimeException(),
            new HttpContext(new ServerRequest('GET', '/')),
        ));
        self::assertFalse($generator->supports(
            new RuntimeException(),
            new CliContext(new ArrayInput([]), new BufferedOutput()),
        ));
    }
}
