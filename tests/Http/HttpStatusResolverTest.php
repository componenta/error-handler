<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Http;

use Componenta\Error\Context\HttpContext;
use Componenta\Error\Http\HttpStatusResolver;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HttpStatusResolverTest extends TestCase
{
    public function testNonIntegerExceptionCodeDoesNotBecomeAnHttpStatus(): void
    {
        $error = new class extends RuntimeException {
            public function __construct()
            {
                parent::__construct('non-HTTP exception');
                $this->code = '404';
            }
        };

        self::assertSame(500, (new HttpStatusResolver(useExceptionCode: true))->resolve(
            $error, new HttpContext(new ServerRequest('GET', '/')),
        ));
    }

    public function testDomainCodesAreNotHttpStatusesByDefault(): void
    {
        $context = new HttpContext(new ServerRequest('GET', '/'));
        $error = new RuntimeException('domain failure', 404);
        self::assertSame(500, (new HttpStatusResolver())->resolve($error, $context));
        self::assertSame(404, (new HttpStatusResolver(useExceptionCode: true))->resolve($error, $context));
        self::assertSame(404, (new HttpStatusResolver())->resolve(new \Componenta\Http\Exception\NotFoundException(), $context));
    }

    public function testProtocolHeadersArePreservedWithoutOverridingRepresentation(): void
    {
        $error = (new \Componenta\Http\Exception\UnauthorizedException())
            ->withHeader('WWW-Authenticate', 'Bearer realm="api"')
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Cache-Control', 'public');
        $response = (new \Componenta\Error\Http\HttpErrorResponseGenerator(new \Nyholm\Psr7\Factory\Psr17Factory()))
            ->generate($error, new HttpContext(new ServerRequest('GET', '/')));
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer realm="api"', $response->getHeaderLine('WWW-Authenticate'));
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }
}
