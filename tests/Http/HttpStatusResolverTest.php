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

        self::assertSame(500, (new HttpStatusResolver())->resolve(
            $error, new HttpContext(new ServerRequest('GET', '/')),
        ));
    }
}
