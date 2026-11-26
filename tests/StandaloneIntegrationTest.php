<?php

declare(strict_types=1);

namespace Componenta\Error\Tests;

use Componenta\Error\Context\CliContext;
use Componenta\Error\ErrorOccurrenceFactory;
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\Provider\ArrayErrorListenerProvider;
use Componenta\Error\Handler\CliErrorHandler;
use Componenta\Error\Handler\HttpErrorHandler;
use Componenta\Error\Http\Middleware\ErrorHandlerMiddleware;
use Componenta\Error\Reporter\ErrorReporter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

final class StandaloneIntegrationTest extends TestCase
{
    public function testHttpAndCliComposeWithoutAFrameworkContainer(): void
    {
        $events = [];
        $listener = ErrorListener::createFrom(static function (ErrorEventInterface $event) use (&$events): void {
            $events[] = $event;
        });
        $reporter = new ErrorReporter(new ArrayErrorListenerProvider([Throwable::class => [$listener]]));
        $factory = new ErrorOccurrenceFactory();
        $exception = new RuntimeException('private failure');
        $middleware = new ErrorHandlerMiddleware(HttpErrorHandler::createDefaults(new Psr17Factory(), reporter: $reporter), errorFactory: $factory);
        $next = new readonly class($exception) implements RequestHandlerInterface {
            public function __construct(private Throwable $exception) {}
            public function handle(ServerRequestInterface $request): ResponseInterface { throw $this->exception; }
        };
        $response = $middleware->process(new ServerRequest('GET', '/', ['Accept' => 'application/json']), $next);
        $output = new BufferedOutput();
        $error = $factory->create($exception);
        CliErrorHandler::createDefaults(reporter: $reporter)->handle($error, new CliContext(new ArrayInput([]), $output));

        self::assertSame(500, $response->getStatusCode());
        self::assertCount(2, $events);
        self::assertSame($exception, $events[0]->error->exception);
        self::assertSame($events[0]->error->id, $response->getHeaderLine('X-Error-ID'));
        self::assertSame($events[0]->error->id, json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['error']['id']);
        self::assertSame($error, $events[1]->error);
        self::assertNotSame($events[0]->error->id, $error->id);
        self::assertStringContainsString('Error ID: ' . $error->id, $output->fetch());
    }
}
