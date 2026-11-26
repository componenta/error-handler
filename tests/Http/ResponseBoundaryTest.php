<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Http;

use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\Handler\HttpErrorHandler;
use Componenta\Error\Http\EmergencyHttpResponseGenerator;
use Componenta\Error\Http\HttpErrorResponseGenerator;
use Componenta\Error\Http\HttpStatusResolverInterface;
use Componenta\Http\Exception\UpgradeRequiredException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\Runtime\PropertyHook;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class ResponseBoundaryTest extends TestCase
{
    public function testInvalidCustomStatusCannotProduceSuccessfulErrorResponse(): void
    {
        $factory = new Psr17Factory();
        $resolver = $this->createStub(HttpStatusResolverInterface::class);
        $resolver->method('resolve')->willReturn(200);
        $generator = new HttpErrorResponseGenerator($factory, statusResolver: $resolver);
        $handler = new HttpErrorHandler($generator, emergencyGenerator: new EmergencyHttpResponseGenerator($factory));
        $response = $handler->handle(new ErrorOccurrence(new RuntimeException('original'), 'ERR-test'), new HttpContext(new ServerRequest('GET', '/')));

        self::assertSame(500, $response->getStatusCode());
        self::assertNotEmpty($response->getHeaderLine('X-Error-ID'));
    }

    public function testHttp11UpgradeExceptionRetainsRequiredProtocolHeader(): void
    {
        $error = new UpgradeRequiredException('websocket');
        $request = (new ServerRequest('GET', '/'))->withProtocolVersion('1.1');
        $response = (new HttpErrorResponseGenerator(new Psr17Factory()))->generate(new ErrorOccurrence($error, 'ERR-test'), new HttpContext($request));

        self::assertSame(426, $response->getStatusCode());
        self::assertSame('websocket', $response->getHeaderLine('Upgrade'));
        self::assertSame('Upgrade', $response->getHeaderLine('Connection'));
    }

    public function testEmergencyResponseDoesNotRequireReadableContextAttributes(): void
    {
        $context = $this->createStub(HttpErrorContextInterface::class);
        $context->method(PropertyHook::get('request'))->willReturn(new ServerRequest('GET', '/'));
        $context->method('getAttribute')->willThrowException(new RuntimeException('unavailable context'));
        $response = (new EmergencyHttpResponseGenerator(new Psr17Factory()))->generate(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), $context);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame("An unexpected error occurred\nError ID: ERR-test", (string) $response->getBody());
        self::assertSame('ERR-test', $response->getHeaderLine('X-Error-ID'));
    }

    public function testEmergencyResponseCompletesPartialStreamWrites(): void
    {
        $written = '';
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('write')->willReturnCallback(static function (string $bytes) use (&$written): int {
            $part = substr($bytes, 0, 3);
            $written .= $part;
            return strlen($part);
        });
        $factory = $this->createStub(ResponseFactoryInterface::class);
        $factory->method('createResponse')->willReturn((new Psr17Factory())->createResponse(500)->withBody($stream));

        (new EmergencyHttpResponseGenerator($factory))->generate(new ErrorOccurrence(new RuntimeException(), 'ERR-test'), new HttpContext(new ServerRequest('GET', '/')));

        self::assertSame("An unexpected error occurred\nError ID: ERR-test", $written);
    }

    public function testContextMetadataFailureDoesNotPreventCorrelationHeader(): void
    {
        $factory = new Psr17Factory();
        $generator = $this->createStub(\Componenta\Error\Http\HttpErrorResponseGeneratorInterface::class);
        $generator->method('generate')->willReturn($factory->createResponse(500));
        $context = $this->createStub(HttpErrorContextInterface::class);
        $context->method('withAttribute')->willThrowException(new RuntimeException('context write failed'));

        $response = (new HttpErrorHandler($generator))->handle(new ErrorOccurrence(new RuntimeException(), 'ERR-custom'), $context);
        self::assertSame('ERR-custom', $response->getHeaderLine('X-Error-ID'));
        self::assertSame(500, $response->getStatusCode());
    }

    public function testLargeResponseCompletesShortWritesWithoutChangingContent(): void
    {
        $body = str_repeat('0123456789', 2000);
        $actual = '';
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('write')->willReturnCallback(static function (string $bytes) use (&$actual): int {
            self::assertLessThanOrEqual(8192, strlen($bytes));
            $part = substr($bytes, 0, 17);
            $actual .= $part;
            return strlen($part);
        });
        $renderer = $this->createStub(\Componenta\Error\Renderer\ErrorRendererInterface::class);
        $renderer->method('supports')->willReturn(true);
        $renderer->method('render')->willReturn(new \Componenta\Error\Renderer\RenderedError($body, 'text/plain'));
        $factory = $this->createStub(ResponseFactoryInterface::class);
        $factory->method('createResponse')->willReturn((new Psr17Factory())->createResponse(500)->withBody($stream));
        (new HttpErrorResponseGenerator($factory, $renderer))->generate(
            new ErrorOccurrence(new RuntimeException(), 'ERR-test'),
            new HttpContext(new ServerRequest('GET', '/')),
        );
        self::assertSame($body, $actual);
    }

    public function testRendererDecliningResolvedHttpStatusFallsBackWithoutResolvingStatusTwice(): void
    {
        $error = new ErrorOccurrence(new RuntimeException('private'), 'ERR-declined');
        $context = new HttpContext(new ServerRequest('GET', '/', ['Accept' => 'application/json']));
        $resolver = $this->createMock(HttpStatusResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->with($error->exception, $context)->willReturn(404);
        $renderer = $this->createMock(\Componenta\Error\Renderer\ErrorRendererInterface::class);
        $renderer->expects(self::once())->method('supports')->willReturnCallback(
            static function (ErrorOccurrence $occurrence, \Componenta\Error\ErrorContextInterface $resolved) use ($error): bool {
                self::assertSame($error, $occurrence);
                self::assertSame(404, $resolved->getAttribute(\Componenta\Error\Context\ErrorContextAttribute::HTTP_STATUS_CODE));
                return false;
            },
        );
        $renderer->expects(self::never())->method('render');
        $generator = new HttpErrorResponseGenerator(new Psr17Factory(), $renderer, $resolver);
        self::assertTrue($generator->supports($error, $context));
        $response = $generator->generate($error, $context);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('ERR-declined', $response->getHeaderLine('X-Error-ID'));
        self::assertSame(404, json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['error']['status']);
        self::assertStringNotContainsString('private', (string) $response->getBody());
    }

    public function testVaryWildcardAndExistingAcceptArePreserved(): void
    {
        $generator = new HttpErrorResponseGenerator(new Psr17Factory());
        $context = new HttpContext(new ServerRequest('GET', '/'));
        foreach (['*' => '*', 'Origin, aCcEpT' => 'Origin, aCcEpT', 'Origin' => 'Origin, Accept'] as $value => $expected) {
            $exception = (new \Componenta\Http\Exception\NotFoundException())->withHeader('Vary', $value);
            $response = $generator->generate(new ErrorOccurrence($exception, 'ERR-vary'), $context);
            self::assertSame($expected, $response->getHeaderLine('Vary'));
        }
    }

    public function testRepresentationDigestAndInapplicableContentRangeAreNotCopied(): void
    {
        $exception = (new \Componenta\Http\Exception\MethodNotAllowedException(['GET']))
            ->withHeader('Content-Digest', 'sha-256=:YWJj:')
            ->withHeader('Repr-Digest', 'sha-256=:YWJj:')
            ->withHeader('Digest', 'SHA-256=YWJj')
            ->withHeader('Content-MD5', 'YWJj')
            ->withHeader('Content-Range', 'bytes 0-1/3')
            ->withHeader('TE', 'trailers')
            ->withHeader('Proxy-Connection', 'keep-alive');
        $generator = new HttpErrorResponseGenerator(new Psr17Factory());
        $context = new HttpContext(new ServerRequest('POST', '/'));
        $response = $generator->generate(new ErrorOccurrence($exception, 'ERR-fields'), $context);
        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET', $response->getHeaderLine('Allow'));
        foreach (['Content-Digest', 'Repr-Digest', 'Digest', 'Content-MD5', 'Content-Range', 'TE', 'Proxy-Connection'] as $field) {
            self::assertFalse($response->hasHeader($field));
        }

        $range = $generator->generate(
            new ErrorOccurrence(new \Componenta\Http\Exception\RangeNotSatisfiableException(1024), 'ERR-range'),
            $context,
        );
        self::assertSame(416, $range->getStatusCode());
        self::assertSame('bytes */1024', $range->getHeaderLine('Content-Range'));
    }
}
