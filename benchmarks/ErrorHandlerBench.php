<?php

declare(strict_types=1);

namespace Componenta\Error\Benchmarks;

use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Context\CliContext;
use Componenta\Error\Context\ErrorContextAttribute;
use Componenta\Error\Event\ErrorEvent;
use Componenta\Error\Listener\Psr3ErrorListener;
use Psr\Log\NullLogger;
use DateTimeImmutable;
use Componenta\Error\Context\HttpContext;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\Provider\ArrayErrorListenerProvider;
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
    private PrettyPageRenderer $prettyWithSource;
    private HttpContext $largeContext;
    private RequestHandlerInterface $failingNext;
    private \Componenta\Error\Http\EmergencyHttpResponseGenerator $emergency;
    /** @var array<int, ErrorReporter> */
    private array $reporters;
    private ConsoleErrorRenderer $console;
    private ErrorEvent $logEvent;
    private Psr3ErrorListener $psr3;
    private Psr3ErrorListener $psr3WithException;

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
        $this->prettyWithSource = new PrettyPageRenderer();
        $large = array_fill(0, 100, str_repeat('a', 4096));
        $this->largeContext = new HttpContext((new ServerRequest('POST', '/'))->withQueryParams($large)->withParsedBody($large));
        $this->emergency = new \Componenta\Error\Http\EmergencyHttpResponseGenerator($this->factory);
        $this->failingNext = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('benchmark failure');
            }
        };
        $this->console = new ConsoleErrorRenderer(colors: false, terminalWidth: 100);
        $this->logEvent = new ErrorEvent(
            $this->error,
            $this->json->withAttribute(ErrorContextAttribute::HTTP_STATUS_CODE, 500),
            new DateTimeImmutable('2026-02-03T04:05:06+00:00'),
        );
        $logger = new NullLogger();
        $this->psr3 = new Psr3ErrorListener($logger);
        $this->psr3WithException = new Psr3ErrorListener($logger, includeException: true);
        $this->reporters = [];
        foreach ([0, 1, 10, 100] as $size) {
            $reporter = $this->newReporter($size);
            $reporter->report($this->error, $this->json);
            $this->reporters[$size] = $reporter;
        }
    }

    public function benchSuccessfulMiddleware(): void
    {
        $this->middleware->process($this->html->request, $this->next);
    }

    public function benchMiddlewareException(): void
    {
        $this->middleware->process($this->json->request, $this->failingNext);
    }

    public function benchEmergencyResponse(): void
    {
        $this->emergency->generate($this->error, $this->json);
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

    public function benchPrettyHtmlWithSource(): void
    {
        $this->prettyWithSource->render($this->error, $this->html);
    }

    public function benchPrettyLargeData(): void
    {
        $this->pretty->render($this->error, $this->largeContext);
    }

    public function benchConsole(): void
    {
        $this->console->render($this->error, $this->cli);
    }

    // Measure listener metadata preparation, not a particular backend's I/O.
    public function benchPsr3Listener(): void
    {
        $this->psr3->handleEvent($this->logEvent);
    }

    public function benchPsr3ListenerWithException(): void
    {
        $this->psr3WithException->handleEvent($this->logEvent);
    }

    #[Bench\ParamProviders('provideFanOut')]
    public function benchReporter(array $params): void
    {
        $this->reporters[$params['listeners']]->report($this->error, $this->json);
    }

    #[Bench\ParamProviders('provideFanOut')]
    public function benchReporterConstructionAndDispatch(array $params): void
    {
        $this->newReporter($params['listeners'])->report($this->error, $this->json);
    }

    private function newReporter(int $size): ErrorReporter
    {
        $listeners = [];
        for ($i = 0; $i < $size; $i++) {
            $listeners[] = ErrorListener::createFrom(static function (): void {});
        }
        return new ErrorReporter(new ArrayErrorListenerProvider([\Throwable::class => $listeners]));
    }

    public function provideFanOut(): iterable
    {
        foreach ([0, 1, 10, 100] as $listeners) {
            yield 'listeners-' . $listeners => ['listeners' => $listeners];
        }
    }
}
