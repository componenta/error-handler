<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Context;

use Componenta\Error\Context\CliContext;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Context\HttpContext;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class NumericAttributesTest extends TestCase
{
    /** @param array<string|int, mixed> $expected */
    #[DataProvider('singleUpdates')]
    public function testSingleUpdatePreservesAttributeKeys(string $type, string|int $key, array $expected): void
    {
        $context = self::context($type);

        $updated = $context->withAttribute($key, 'updated');

        self::assertSame($expected, $updated->getAttributes());
        self::assertSame('updated', $updated->getAttribute($key));
        self::assertSame([7 => 'old', -4 => 'negative', 'name' => 'kept'], $context->getAttributes());
    }

    /** @return iterable<string, array{string, string|int, array<string|int, mixed>}> */
    public static function singleUpdates(): iterable
    {
        foreach (['http', 'cli'] as $type) {
            yield $type . ' replaces positive key' => [$type, 7, [7 => 'updated', -4 => 'negative', 'name' => 'kept']];
            yield $type . ' replaces negative key' => [$type, -4, [7 => 'old', -4 => 'updated', 'name' => 'kept']];
            yield $type . ' updates string key' => [$type, 'name', [7 => 'old', -4 => 'negative', 'name' => 'updated']];
            yield $type . ' adds numeric key' => [$type, 13, [7 => 'old', -4 => 'negative', 'name' => 'kept', 13 => 'updated']];
        }
    }

    #[DataProvider('contextTypes')]
    public function testBulkUpdateReplacesAndAddsAttributesByTheirKeys(string $type): void
    {
        $context = self::context($type);

        $updated = $context->withAttributes([7 => null, 'name' => 'updated', 13 => 'added']);

        self::assertSame([7 => null, -4 => 'negative', 'name' => 'updated', 13 => 'added'], $updated->getAttributes());
        self::assertNull($updated->getAttribute(7, 'missing'));
        self::assertSame([7 => 'old', -4 => 'negative', 'name' => 'kept'], $context->getAttributes());
    }

    #[DataProvider('contextTypes')]
    public function testEmptyUpdatePreservesNumericKeys(string $type): void
    {
        $updated = self::context($type)->withAttributes([]);

        self::assertSame([7 => 'old', -4 => 'negative', 'name' => 'kept'], $updated->getAttributes());
    }

    #[DataProvider('contextTypes')]
    public function testAttributeBindingsDetachCallerReferenceSlots(string $type): void
    {
        $value = 'original';
        $attributes = ['key' => &$value, 4 => &$value];
        $context = $type === 'http'
            ? new HttpContext(new ServerRequest('GET', '/'), $attributes)
            : new CliContext(new ArrayInput([]), new BufferedOutput(), $attributes);
        $next = $context->withAttributes(['extra' => &$value]);
        $value = 'caller mutation';
        $returned = $context->getAttributes();
        $returned['key'] = 'returned mutation';

        self::assertSame('original', $context->getAttribute('key'));
        self::assertSame('original', $context->getAttribute(4));
        self::assertSame('original', $next->getAttribute('extra'));

        // Shared object identity is intentional, unlike the array slot alias.
        $object = new \stdClass();
        self::assertSame($object, $context->withAttribute('object', $object)->getAttribute('object'));
    }

    /** @return iterable<string, array{string}> */
    public static function contextTypes(): iterable
    {
        yield 'http' => ['http'];
        yield 'cli' => ['cli'];
    }

    private static function context(string $type): ErrorContextInterface
    {
        $attributes = [7 => 'old', -4 => 'negative', 'name' => 'kept'];

        return $type === 'http'
            ? new HttpContext(new ServerRequest('GET', '/'), $attributes)
            : new CliContext(new ArrayInput([]), new BufferedOutput(), $attributes);
    }
}
