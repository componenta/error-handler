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
$fallback = new HttpErrorResponseGenerator($responseFactory);
$handler = new HttpErrorHandler($fallback);

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

`HttpErrorResponseGenerator` resolves the response status once through `HttpStatusResolver` and writes it to the context as `ErrorContextAttribute::HTTP_STATUS_CODE` before rendering. The default resolver uses `HttpStatusAwareInterface`, then an exception code in the `400..599` range, then `500`.

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
- `PlainTextRenderer`
- `ConsoleErrorRenderer`
- `NullRenderer`
- `CompositeErrorRenderer`

Fallback generators use safe defaults: HTTP uses `SafeRenderer`, CLI uses `PlainTextRenderer`.

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

$handler->addListener(
    ErrorListener::createFrom(static function (ErrorEventInterface $event): void {
        // log or report $event->exception and $event->context
    }),
    priority: 100,
);
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
| `ConfigKey::ERROR_REPORTER` | Reporter service. |
| `ConfigKey::ERROR_ID_GENERATOR` | Error id generator service. |
| `ConfigKey::ERROR_LEVEL` | Error level handled by `ErrorHandlerMiddleware`. |

If no `ConfigKey::HTTP_FALLBACK_GENERATOR` is configured, the default HTTP handler requires `Psr\Http\Message\ResponseFactoryInterface` in the container.
