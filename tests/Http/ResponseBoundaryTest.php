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
}
