<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Renderer;

use Componenta\Error\Context\HttpContext;
use Componenta\Error\Renderer\JsonRenderer;
use Componenta\Error\Renderer\SafeRenderer;
use Componenta\Error\Renderer\HtmlRenderer;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvalidEncodingTest extends TestCase
{
    public function testJsonRemainsValidWithInvalidUtf8(): void
    {
        $context = new HttpContext(new ServerRequest('GET', '/', ['Accept' => 'application/json']));
        $message = "before\xFF-after";
        $error = new RuntimeException($message);

        $debug = json_decode((new JsonRenderer())->render($error, $context), true, flags: JSON_THROW_ON_ERROR);
        $safe = json_decode((new SafeRenderer(defaultMessage: $message))->render($error, $context), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame("before\u{FFFD}-after", $debug['error']['message']);
        self::assertSame("before\u{FFFD}-after", $safe['error']['message']);
        self::assertStringContainsString('before', (new HtmlRenderer())->render($error, $context));
    }
}
