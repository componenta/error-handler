# Componenta Error Handler

HTTP and CLI error handling for PHP 8.4+ applications.

The package contains the reusable error handling layer. It does not choose a concrete PSR-7/PSR-17 implementation. HTTP applications must register `Psr\Http\Message\ResponseFactoryInterface` through one of the `componenta/http-psr-*` integration packages or provide a custom `HttpErrorResponseGeneratorInterface`.

## Installation

```bash
composer require componenta/error-handler
```

The package declares `Componenta\Error\ConfigProvider` in `extra.componenta.config-providers`.
When `componenta/composer-plugin` is installed, the provider is added to the generated provider list automatically.

## Related Packages

| Package | Why it matters here |
|---|---|
| `componenta/http` | Provides HTTP exception types; PSR request/response integration is implemented by the HTTP error handler and app packages. |
| `componenta/pipeline` | Usually runs `ErrorHandlerMiddleware` as the outermost HTTP middleware. |
| `componenta/event` | Can be used for error reporting listeners. |
| `psr/log` | Fits logging listeners and reporters. |
| `componenta/config` | Registers default HTTP/CLI handlers, renderers, and reporters. |
| `componenta/http-psr-nyholm`, `componenta/http-psr-diactoros`, `componenta/http-psr-guzzle`, or `componenta/http-psr-slim` | Registers the concrete PSR-17 factories used by HTTP response generation. |

## What It Provides

- HTTP and CLI aggregate error handlers.
- Fallback response generator for exceptions not handled by a specialized generator. The generator still requires an explicit PSR-17 `ResponseFactoryInterface`.
- Error renderers for safe HTML/JSON, JSON, HTML, plain text, null output, and composite rendering.
- Error reporting listeners for logging, monitoring, and notifications.
- Immutable HTTP and CLI contexts.
- PSR-15 middleware for HTTP applications.
- Config provider for framework integration.

## HTTP Usage

```php
use Componenta\Error\Handler\HttpErrorHandler;
use Componenta\Error\Http\HttpErrorResponseGenerator;
use Componenta\Error\Http\Middleware\ErrorHandlerMiddleware;
use Psr\Http\Message\ResponseFactoryInterface;

/** @var ResponseFactoryInterface $responseFactory */
$handler = HttpErrorHandler::createDefaults($responseFactory);

$middleware = new ErrorHandlerMiddleware($handler);
$response = $middleware->process($request, $next);
```

Register specialized generators with priorities:

```php
$handler->addGenerator(new NotFoundGenerator($responseFactory), priority: 100);
$handler->addGenerator(new ValidationGenerator($responseFactory), priority: 50);
```

Higher priority generators are checked first.

Custom generators implement `HttpErrorResponseGeneratorInterface`:

```php
use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\Http\HttpErrorResponseGeneratorInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final readonly class ValidationErrorResponseGenerator implements HttpErrorResponseGeneratorInterface
{
    public function supports(Throwable $exception, ErrorContextInterface $context): bool
    {
        return $context instanceof HttpErrorContextInterface
            && $exception instanceof InvalidArgumentException;
    }

    public function generate(Throwable $exception, HttpErrorContextInterface $context): ResponseInterface
    {
        // Build and return a PSR-7 response.
    }
}
```

`HttpErrorResponseGenerator` resolves the response status once through `HttpStatusResolver` and writes it to the context as `ErrorContextAttribute::HTTP_STATUS_CODE` before rendering. The default resolver uses typed HTTP exceptions (`HttpStatusAwareInterface` or `componenta/http` exceptions) and otherwise returns 500. Numeric exception codes are opt-in with `new HttpStatusResolver(useExceptionCode: true)`.

## CLI Usage

```php
use Componenta\Error\Context\CliContext;
use Componenta\Error\Handler\CliErrorHandler;
use Componenta\Error\Handler\RenderingCliErrorHandler;

$handler = new CliErrorHandler(new RenderingCliErrorHandler());

try {
    $app->run();
} catch (Throwable $exception) {
    $handler->handle($exception, CliContext::fromArgv());
    exit(1);
}
```

## Renderers

Built-in renderers:

- `SafeRenderer`
- `JsonRenderer`
- `HtmlRenderer`
- `PrettyPageRenderer`
- `SafeCliRenderer`
- `PlainTextRenderer`
- `ConsoleErrorRenderer`
- `NullRenderer`
- `CompositeErrorRenderer`

Fallback generators use safe defaults: HTTP uses `SafeRenderer`, CLI uses `SafeCliRenderer`.

Additional renderer integrations live in separate packages:

| Package | Renderer | Typical use |
|---|---|---|
| [`componenta/error-renderer-plates`](../error-renderer-plates/README.md) | `PlatesRenderer` | Safe or custom HTML error pages rendered with League Plates templates. |
| [`componenta/error-renderer-whoops`](../error-renderer-whoops/README.md) | `WhoopsRenderer` | Detailed development error pages through Whoops. |
| [`componenta/error-renderer-symfony`](../error-renderer-symfony/README.md) | `SymfonyRenderer` | Symfony error page rendering adapted to `ErrorRendererInterface`. |
| [`componenta/error-renderer-ignition`](../error-renderer-ignition/README.md) | `IgnitionRenderer` | Spatie Ignition development error pages. |
| [`componenta/error-renderer-collision`](../error-renderer-collision/README.md) | `CollisionRenderer` | Rich CLI error output through Collision. |

## Contexts

Contexts carry request or command-line information plus arbitrary attributes.

```php
$context = $context->withAttribute('user_id', 123);
$userId = $context->getAttribute('user_id');
```

Context objects are immutable: `withAttribute()` and `withAttributes()` return a new instance.

`HttpContext::fromGlobals()` does not create PSR-17 factories by itself. Pass a `Nyholm\Psr7Server\ServerRequestCreatorInterface` when a context must be built from PHP superglobals:

```php
$context = HttpContext::fromGlobals($serverRequestCreator);
```

## Reporting

Register listeners for logging or monitoring:

```php
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;

$listeners = new \Componenta\Error\Event\ErrorListenerProvider();
$listeners->addListener(
    ErrorListener::createFrom(static function (ErrorEventInterface $event): void {
        // log or report $event->exception and $event->context
    }),
    priority: 100,
);
$reporter = new \Componenta\Error\Reporter\ErrorReporter($listeners);
$handler = HttpErrorHandler::createDefaults($responseFactory, reporter: $reporter);
```

Specific exceptions can be excluded from reporting by class name or predicate.

`ErrorReporter` accepts a `ListenerExceptionPolicy`. The default policy swallows listener failures and writes them through `error_log()`, so error reporting cannot replace the original application exception with a logging failure.

## DI Registration

`ConfigProvider` registers the HTTP/CLI handlers, renderers, reporter, and middleware dependencies needed by Componenta applications.

Important config keys:

| Key | Purpose |
|---|---|
| `ConfigKey::HTTP_FALLBACK_GENERATOR` | Service id for the fallback `HttpErrorResponseGeneratorInterface`. |
| `ConfigKey::HTTP_RENDERER` | Renderer used by the default HTTP response generator. |
| `ConfigKey::HTTP_GENERATORS` | Ordered service ids for specialized HTTP response generators. |
| `ConfigKey::HTTP_LISTENERS` | HTTP error listeners. |
| `ConfigKey::CLI_FALLBACK_HANDLER` | CLI fallback renderer/handler. |
| `ConfigKey::CLI_RENDERER` | CLI renderer. |
| `ConfigKey::CLI_LISTENERS` | CLI error listeners. |
| `ConfigKey::HTTP_REPORTER` / `CLI_REPORTER` | Independent transport-specific reporters. |
| `ConfigKey::ERROR_REPORTER` | Explicit shared reporter override. |
| `ConfigKey::ERROR_ID_GENERATOR` | Error id generator service. |
| `ConfigKey::ERROR_LEVEL` | Error level handled by `ErrorHandlerMiddleware`. |

If no `ConfigKey::HTTP_FALLBACK_GENERATOR` is configured, the default HTTP handler requires `Psr\Http\Message\ResponseFactoryInterface` in the container.

## Version 2 migration

Requires Config 4 and PHP 8.4 or later. Register listeners on `ErrorListenerProvider` and exclusion predicates on `ErrorReporter`, rather than on handlers. Context attributes are accessible only through the context contract. All renderers implement `ErrorRendererInterface::render()` and return an immutable, `Stringable` `RenderedError` with `content` and `contentType`. The caller selects the transport; the renderer declares the actual media type. Typed HTTP exceptions replace implicit numeric code mapping. The default handlers isolate reporting failures and use an independent emergency response when a PSR-17 factory is available. Error IDs have 128 bits of random entropy; HTTP responses expose the correlation ID via `X-Error-ID`. Development renderers can expose confidential exception messages or source; use SafeRenderer in production. Never expose HTML log snapshots publicly. Configure handlers and listeners before handling requests, not during requests. Middleware restores PHP error-handler state but cannot intercept fatal process termination or guarantee isolation between overlapping fiber executions.

## Verification

`composer test` runs unit tests with Pest. `composer phpstan` runs static analysis. `composer benchmark` runs PHPBench. The integration suite requires DI 6 (PHP 8.5); install it separately with `composer require --dev 'componenta/di:^6.0@dev'`, then run `composer test:integration`. The CI matrix also tests the five renderer adapters against the same core checkout.

The default CLI handler uses `SafeCliRenderer` (generic message and correlation ID). Choose `PlainTextRenderer` or `ConsoleErrorRenderer` explicitly for diagnostic output; their exception messages are not suitable for untrusted recipients.

Recursive calls to the same `ErrorReporter` within one execution context are ignored while its listeners or filters are running. Separate fibers can report independently.

## Renderer result

```php
use Componenta\Error\Renderer\JsonRenderer;

$result = (new JsonRenderer())->render($exception, $context);
$result->content;      // Rendered JSON
$result->contentType;  // application/json; charset=utf-8
(string) $result;     // Same as content
```

Custom renderers must return `new RenderedError($content, $contentType)`. `CompositeErrorRenderer` forwards the selected result unchanged. HTTP generators use its content and type directly, while CLI handlers write only its content. There is no separate HTTP rendering contract or implicit string-only fallback.

`ErrorReporter` timestamps each event using an injected PSR-20 `ClockInterface` (UTC system clock by default). The event stores the supplied timestamp. It contains no transport result; the actual HTTP status, if available, is supplied in `ErrorContextAttribute::HTTP_STATUS_CODE`.
