<?php

declare(strict_types=1);

namespace Componenta\Error\Benchmarks;

use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Context\CliContext;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\ErrorListenerProvider;
use Componenta\Error\Handler\HttpErrorHandler;
use Componenta\Error\Http\Middleware\ErrorHandlerMiddleware;
use Componenta\Error\Renderer\ConsoleErrorRenderer;
use Componenta\Error\Renderer\PrettyPageRenderer;
use Componenta\Error\Renderer\SafeRenderer;
use Componenta\Error\Reporter\ErrorReporter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PhpBench\Attributes as Bench;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[Bench\BeforeMethods('setUp')]
#[Bench\Revs(100)]
#[Bench\Iterations(5)]
final class ErrorHandlerBench
{
    private Psr17Factory $factory;
    private HttpErrorHandler $handler;
    private ErrorHandlerMiddleware $middleware;
    private RequestHandlerInterface $next;
    private HttpContext $html;
    private HttpContext $json;
    private CliContext $cli;
    private ErrorOccurrence $error;
    private SafeRenderer $safe;
    private PrettyPageRenderer $pretty;
    private ConsoleErrorRenderer $console;

    public function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->handler = HttpErrorHandler::createDefaults($this->factory);
        $this->middleware = new ErrorHandlerMiddleware($this->handler);
        $this->next = new readonly class($this->factory) implements RequestHandlerInterface {
            public function __construct(private Psr17Factory $factory) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->factory->createResponse(204);
            }
        };
        $this->html = new HttpContext(new ServerRequest('GET', '/'));
        $this->json = new HttpContext(new ServerRequest('GET', '/', ['Accept' => 'application/json']));
        $this->cli = new CliContext(new ArrayInput([]), new BufferedOutput());
        $this->error = new ErrorOccurrence(new RuntimeException('benchmark error'), 'ERR-bench');
        $this->safe = new SafeRenderer();
        $this->pretty = new PrettyPageRenderer(includeSource: false);
        $this->console = new ConsoleErrorRenderer(colors: false, terminalWidth: 100);
    }

    public function benchSuccessfulMiddleware(): void
    {
        $this->middleware->process($this->html->request, $this->next);
    }

    public function benchWarmHttpError(): void
    {
        $this->handler->handle($this->error, $this->json);
    }

    public function benchColdHttpError(): void
    {
        HttpErrorHandler::createDefaults($this->factory)->handle($this->error, $this->json);
    }

    public function benchSafeHtml(): void
    {
        $this->safe->render($this->error, $this->html);
    }

    public function benchSafeJson(): void
    {
        $this->safe->render($this->error, $this->json);
    }

    public function benchPrettyHtml(): void
    {
        $this->pretty->render($this->error, $this->html);
    }

    public function benchConsole(): void
    {
        $this->console->render($this->error, $this->cli);
    }

    #[Bench\ParamProviders('provideFanOut')]
    public function benchReporter(array $params): void
    {
        $listeners = [];
        for ($i = 0; $i < $params['listeners']; $i++) {
            $listeners[] = ErrorListener::createFrom(static function (): void {});
        }
        (new ErrorReporter(new ErrorListenerProvider($listeners)))->report($this->error, $this->json);
    }

    public function provideFanOut(): iterable
    {
        foreach ([0, 1, 10, 100] as $listeners) {
            yield 'listeners-' . $listeners => ['listeners' => $listeners];
        }
    }
}
