<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Context;

use Componenta\Error\Context\CliContext;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[TestDox('CliContext')]
final class CliContextTest extends TestCase
{
    public function testWithAttributeReturnsNewInstanceWithAttribute(): void
    {
        $context = new CliContext(new ArrayInput([]), new BufferedOutput());

        $newContext = $context->withAttribute('key', 'value');

        self::assertNotSame($context, $newContext);
        self::assertSame('value', $newContext->getAttribute('key'));
    }

    public function testWithAttributesPreservesPreviousAttributes(): void
    {
        $context = new CliContext(
            new ArrayInput([]),
            new BufferedOutput(),
            ['first' => 1],
        );

        $newContext = $context->withAttributes(['second' => 2, 'third' => 3]);

        self::assertSame(1, $newContext->getAttribute('first'));
        self::assertSame(2, $newContext->getAttribute('second'));
        self::assertSame(3, $newContext->getAttribute('third'));
    }

    public function testGetAttributeReturnsDefaultWhenKeyNotFound(): void
    {
        $context = new CliContext(new ArrayInput([]), new BufferedOutput());

        self::assertSame('default', $context->getAttribute('missing', 'default'));
    }

    public function testGetAttributeThrowsExceptionWhenKeyNotFoundAndNoDefault(): void
    {
        $context = new CliContext(new ArrayInput([]), new BufferedOutput());

        $this->expectException(InvalidArgumentException::class);

        $context->getAttribute('missing');
    }

    public function testGetAttributesReturnsAllAttributes(): void
    {
        $context = new CliContext(
            new ArrayInput([]),
            new BufferedOutput(),
            ['first' => 1, 'second' => 2],
        );

        self::assertSame(['first' => 1, 'second' => 2], $context->getAttributes());
    }
}