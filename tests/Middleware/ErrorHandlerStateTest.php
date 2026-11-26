<?php

declare(strict_types=1);

namespace Componenta\Error\Tests\Middleware;

use Componenta\Error\Handler\HttpErrorHandler;
use Componenta\Error\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ErrorHandlerStateTest extends TestCase
{
    public function testMaskAndSuppressionAreRespectedAndPreviousHandlerRestored(): void
    {
        $factory = new Psr17Factory();
        $middleware = new ErrorHandlerMiddleware(HttpErrorHandler::createDefaults($factory));
        $calls = [];
        $previous = static function (int $severity, string $message) use (&$calls): bool {
            $calls[] = $message;
            return true;
        };
        $oldLevel = error_reporting(E_USER_NOTICE);
        set_error_handler($previous);

        try {
            $next = new readonly class($factory) implements RequestHandlerInterface {
                public function __construct(private Psr17Factory $factory) {}
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    @trigger_error('suppressed', E_USER_NOTICE);
                    trigger_error('masked', E_USER_WARNING);
                    return $this->factory->createResponse(204);
                }
            };
            $response = $middleware->process(new ServerRequest('GET', '/'), $next);
            self::assertSame(204, $response->getStatusCode());
            self::assertSame(E_USER_NOTICE, error_reporting());
            trigger_error('after', E_USER_NOTICE);
            self::assertSame(['after'], $calls);
        } finally {
            restore_error_handler();
            error_reporting($oldLevel);
        }
    }
}
