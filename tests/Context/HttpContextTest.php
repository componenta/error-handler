<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Context;

use Componenta\Error\Context\HttpContext;
use InvalidArgumentException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('HttpContext')]
final class HttpContextTest extends TestCase
{
    public function testWithAttributeReturnsNewInstanceWithAttribute(): void
    {
        $context = new HttpContext(new ServerRequest('GET', '/'));

        $newContext = $context->withAttribute('key', 'value');

        self::assertNotSame($context, $newContext);
        self::assertSame('value', $newContext->getAttribute('key'));
    }

    public function testWithAttributesPreservesPreviousAttributes(): void
    {
        $context = new HttpContext(new ServerRequest('GET', '/'), ['first' => 1]);

        $newContext = $context->withAttributes(['second' => 2, 'third' => 3]);

        self::assertSame(1, $newContext->getAttribute('first'));
        self::assertSame(2, $newContext->getAttribute('second'));
        self::assertSame(3, $newContext->getAttribute('third'));
    }

    public function testGetAttributeReturnsDefaultWhenKeyNotFound(): void
    {
        $context = new HttpContext(new ServerRequest('GET', '/'));

        self::assertSame('default', $context->getAttribute('missing', 'default'));
    }

    public function testGetAttributeThrowsExceptionWhenKeyNotFoundAndNoDefault(): void
    {
        $context = new HttpContext(new ServerRequest('GET', '/'));

        $this->expectException(InvalidArgumentException::class);

        $context->getAttribute('missing');
    }

    public function testGetAttributesReturnsAllAttributes(): void
    {
        $context = new HttpContext(
            new ServerRequest('GET', '/'),
            ['first' => 1, 'second' => 2],
        );

        self::assertSame(['first' => 1, 'second' => 2], $context->getAttributes());
    }
}