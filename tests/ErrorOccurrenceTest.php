<?php

declare(strict_types=1);

namespace Componenta\Error\Tests;

use Componenta\Error\ErrorId\ErrorIdGeneratorInterface;
use Componenta\Error\ErrorId\RandomErrorIdGenerator;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\ErrorOccurrenceFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorOccurrenceTest extends TestCase
{
    public function testRepeatedOccurrencesOfTheSameThrowableHaveIndependentIds(): void
    {
        $exception = new RuntimeException('original');
        $factory = new ErrorOccurrenceFactory();
        $first = $factory->create($exception);
        $second = $factory->create($exception);

        self::assertSame($exception, $first->exception);
        self::assertSame($exception, $second->exception);
        self::assertNotSame($first->id, $second->id);
        self::assertMatchesRegularExpression('/^ERR-[A-F0-9]{32}$/', $first->id);
    }

    public function testCustomGeneratorIsInvokedOnceAndResultPreserved(): void
    {
        $generator = $this->createMock(ErrorIdGeneratorInterface::class);
        $generator->expects(self::once())->method('generate')->willReturn('custom-123');
        $exception = new RuntimeException();
        $error = (new ErrorOccurrenceFactory($generator))->create($exception);
        self::assertSame('custom-123', $error->id);
        self::assertSame($exception, $error->exception);
    }

    public function testInvalidCustomIdFallsBackToRandomGenerator(): void
    {
        $generator = $this->createStub(ErrorIdGeneratorInterface::class);
        $generator->method('generate')->willReturn("unsafe\r\nheader");
        $error = (new ErrorOccurrenceFactory($generator))->create(new RuntimeException());
        self::assertMatchesRegularExpression('/^ERR-[A-F0-9]{32}$/', $error->id);
    }

    #[DataProvider('invalidIds')]
    public function testRejectsInvalidIds(string $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ErrorOccurrence(new RuntimeException(), $id);
    }

    public static function invalidIds(): iterable
    {
        yield [''];
        yield [' '];
        yield ["bad\nvalue"];
        yield [str_repeat('a', 129)];
    }

    public function testRandomGeneratorSupportsExplicitPrefix(): void
    {
        self::assertMatchesRegularExpression('/^APP-[A-F0-9]{32}$/', (new RandomErrorIdGenerator('APP'))->generate());
    }
}
