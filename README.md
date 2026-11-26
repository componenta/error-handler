# Componenta Error Handler

HTTP and CLI error handling for PHP 8.4+ applications.

The package contains the reusable error handling layer. It does not choose a concrete PSR-7/PSR-17 implementation. HTTP applications must register `Psr\Http\Message\ResponseFactoryInterface` through one of the `componenta/http-psr-*` integration packages or provide a custom `HttpErrorResponseGeneratorInterface`.

## Installation

```bash
composer require componenta/error-handler
```


## Related Packages

| Package | Why it matters here |
|---|---|
| `componenta/http` | Provides HTTP exception types; PSR request/response integration is implemented by the HTTP error handler and app packages. |
| `componenta/pipeline` | Usually runs `ErrorHandlerMiddleware` as the outermost HTTP middleware. |
| `componenta/event` | Can be used for error reporting listeners. |
| `psr/log` | Fits logging listeners and reporters. |
| `componenta/http-psr-nyholm`, `componenta/http-psr-diactoros`, `componenta/http-psr-guzzle`, or `componenta/http-psr-slim` | Registers the concrete PSR-17 factories used by HTTP response generation. |

## What It Provides

- HTTP and CLI aggregate error handlers.
- Fallback response generator for exceptions not handled by a specialized generator. The generator still requires an explicit PSR-17 `ResponseFactoryInterface`.
- Error renderers for safe HTML/JSON, JSON, HTML, plain text, null output, and composite rendering.
- Error reporting listeners for logging, monitoring, and notifications.
- Immutable HTTP and CLI contexts.
- PSR-15 middleware for HTTP applications.

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

Higher priority generators are checked first. The built-in generator accepts only 4xx/5xx statuses. Explicit custom generators may intentionally recover with a different final status, such as a login redirect. They own the response body (including HEAD semantics), cache policy and representation headers. The aggregate preserves their response and adds the correlation ID on a best-effort basis.

Custom generators implement `HttpErrorResponseGeneratorInterface`:

```php
use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Http\HttpErrorResponseGeneratorInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final readonly class ValidationErrorResponseGenerator implements HttpErrorResponseGeneratorInterface
{
    public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool
    {
        return $context instanceof HttpErrorContextInterface
            && $error->exception instanceof InvalidArgumentException;
    }

    public function generate(ErrorOccurrence $error, HttpErrorContextInterface $context): ResponseInterface
    {
        // Build and return a PSR-7 response.
    }
}
```

`HttpErrorResponseGenerator` resolves the response status once through `HttpStatusResolver` and writes it to the context as `ErrorContextAttribute::HTTP_STATUS_CODE` before rendering. The default resolver uses typed HTTP exceptions (`HttpStatusAwareInterface` or `componenta/http` exceptions) and otherwise returns 500. Numeric exception codes are opt-in with `new HttpStatusResolver(useExceptionCode: true)`.

## CLI Usage

```php
use Componenta\Error\Context\CliContext;
use Componenta\Error\ErrorOccurrenceFactory;
use Componenta\Error\Handler\CliErrorHandler;
use Componenta\Error\Handler\RenderingCliErrorHandler;

$handler = new CliErrorHandler(new RenderingCliErrorHandler());

try {
    $app->run();
} catch (Throwable $exception) {
    $handler->handle((new ErrorOccurrenceFactory())->create($exception), CliContext::fromArgv());
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

The reporter is transport independent. Register its ordered listeners and exclusion policy at construction time:

```php
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\Provider\ArrayErrorListenerProvider;
use Componenta\Error\Reporter\ErrorReporter;
use Componenta\Error\Reporter\Policy\NonReportableErrors;

$listener = ErrorListener::createFrom(static function (ErrorEventInterface $event): void {
    // Use $event->error->id, $event->error->exception, $event->context and $event->timestamp.
});
$reporter = new ErrorReporter(
    new ArrayErrorListenerProvider([Throwable::class => [['listener' => $listener, 'priority' => 100]]]),
    new NonReportableErrors([\InvalidArgumentException::class]),
);
$handler = HttpErrorHandler::createDefaults($responseFactory, reporter: $reporter);
```

A global exclusion rule prevents every listener from receiving the event. Individual listener predicates limit a single destination instead. Rules accept exception class names, lists of class names, and callables receiving (ErrorOccurrence, ErrorContextInterface) and returning bool. A valid Throwable type or list of such types is always interpreted as a class rule; use an explicit Closure if a callable has an ambiguous name. For exclusion predicates, true means exclude; for listener predicates, true means support. Within each priority-based provider, listener order is descending priority and stable for equal priorities; the highest matching registration of the same object wins. A composite preserves provider declaration order rather than globally merging priorities. The provider and policy are immutable after construction.

The default reporter continues if a policy fails, uses UTC system time if the injected clock fails, and isolates individual listener failures. ListenerExceptionPolicy::Throw is an explicit direct-reporter option; HTTP/CLI handlers always protect the original error from reporting failures. Recursive dispatch in the same execution context is suppressed; independent fibers can report separately.

### PSR-3 logging

Register `Psr3ErrorListener` with any application-supplied `Psr\Log\LoggerInterface` (for example, Monolog). It is independent of framework configuration, does not install a logger, and is never enabled implicitly.

```php
use Componenta\Error\Event\Provider\ArrayErrorListenerProvider;
use Componenta\Error\Listener\Psr3ErrorListener;
use Componenta\Error\Reporter\ErrorReporter;

$reporter = new ErrorReporter(new ArrayErrorListenerProvider([
    Throwable::class => [new Psr3ErrorListener($logger)],
]));
```

The listener uses a static PSR-3 message with correlation placeholders. Context includes `error_id`, `exception_type`, the event `timestamp` with microsecond precision and its original UTC offset, `transport` for HTTP/CLI, and `status` when the actual HTTP response status is available. PHP `Error` instances use `critical`, other throwables use `error`; no automatic severity downgrade is applied for HTTP 4xx responses. Neither the raw request nor arbitrary context attributes are forwarded.

To grant a trusted logger access to the original Throwable, explicitly use `new Psr3ErrorListener($logger, includeException: true)`. It is passed unchanged under the PSR-3 `exception` key. This may expose confidential messages, traces, arguments and previous exceptions through the logger's handlers and formatters. The default excludes this object. Logger failures follow the reporter's existing failure policy; the listener does not retry or swallow them. Global reporting policy and provider selection remain outside the listener.

### Non-reportable exception classes

```php
use Componenta\Error\Reporter\Attribute\NonReportable;

#[NonReportable]
final class ExpectedException extends \RuntimeException
{
}
```

`NonReportableErrors`, the default reporting policy, excludes directly annotated exception classes before evaluating configured rules, querying the listener provider, or reading the clock. The marker is non-repeatable, has no parameters, and is not implicitly inherited. Configured class-name exclusions still match descendants. The marker does not affect rendering, HTTP status, CLI output, or occurrence identity. A custom `ErrorReportingPolicyInterface` implementation may choose different semantics. Use the marker for intrinsically non-reportable failures; use application configuration or predicates for deployment-specific decisions. Do not mark exceptions whose security or operational audit records must be retained.

## Framework integration

Install `componenta/error-handler-app` for the Componenta configuration provider, service factories and automatic class discovery. The standalone core has no Config or DI dependency. Construct the handlers and reporter directly when using the library outside the framework.

## Version 2 migration

Requires PHP 8.4 or later. Supply listener registrations to `ArrayErrorListenerProvider` and exclusion rules to `NonReportableErrors` at construction time. Context attributes are accessible only through the context contract. All renderers implement `ErrorRendererInterface::render()` and return an immutable, `Stringable` `RenderedError` with `content` and `contentType`. The caller selects the transport; the renderer declares the actual media type. Typed HTTP exceptions replace implicit numeric code mapping. The default handlers isolate reporting failures and use an independent emergency response when a PSR-17 factory is available. Error IDs have 128 bits of random entropy; HTTP responses expose the correlation ID via `X-Error-ID`. Development renderers can expose confidential exception messages or source; use SafeRenderer in production. Never expose HTML log snapshots publicly. Configure handlers and listeners before handling requests, not during requests. Middleware restores PHP error-handler state but cannot intercept fatal process termination or guarantee isolation between overlapping fiber executions.

## Verification

`composer test` runs unit tests with Pest. `composer phpstan` runs static analysis. `composer benchmark` runs PHPBench. Run `composer test:integration` to verify standalone composition. The CI matrix also tests the five renderer adapters and error-handler-app (including Config 4 and DI 6 integration) against the same core checkout.

The default CLI handler uses `SafeCliRenderer` (generic message and correlation ID). Choose `PlainTextRenderer` or `ConsoleErrorRenderer` explicitly for diagnostic output; their exception messages are not suitable for untrusted recipients.

Recursive calls to the same `ErrorReporter` within one execution context are ignored while its listeners or filters are running. Separate fibers can report independently.

## Renderer result

```php
use Componenta\Error\Renderer\JsonRenderer;

$error = (new \Componenta\Error\ErrorOccurrenceFactory())->create($exception);
$result = (new JsonRenderer())->render($error, $context);
$result->content;      // Rendered JSON
$result->contentType;  // application/json; charset=utf-8
(string) $result;     // Same as content
```

Custom renderers must return `new RenderedError($content, $contentType)`. `CompositeErrorRenderer` forwards the selected result unchanged. HTTP generators use its content and type directly, while CLI handlers write only its content. There is no separate HTTP rendering contract or implicit string-only fallback.

`ErrorReporter` timestamps each event using an injected PSR-20 `ClockInterface` (UTC system clock by default). The event stores the supplied timestamp. It contains no transport result; the actual HTTP status, if available, is supplied in `ErrorContextAttribute::HTTP_STATUS_CODE`.

`ErrorOccurrence` contains the original `Throwable` and a required ID. `ErrorOccurrenceFactory` creates it once at the catch boundary; the default ID is `ERR-` plus 128 random bits in hexadecimal (no wall-clock suffix). All rendering, handling and reporting contracts take the same occurrence. `HttpStatusResolver` still takes the original exception. Error IDs are not context attributes. The error-handler-app configuration shares `ErrorReporterInterface` across HTTP and CLI; override the interface service to supply a different reporter, policy, listener provider or ID generator. `ErrorEvent` contains only `error`, `context` and `timestamp`.

## Listener providers

All providers expose only provideFor(Throwable): iterable<ErrorListenerInterface>. ArrayErrorListenerProvider accepts a map from Throwable types to lists of listener instances or ['listener' => instance, 'priority' => int]. Throwable::class is the catch-all. Parent classes and Throwable-compatible interfaces match. AttributeErrorListenerProvider indexes #[ListenTo(ExceptionType::class, priority: 10)] on supplied concrete listener classes once and invokes the provided Closure resolver only for matching classes. Repeated matching attributes select the highest priority. Attributes are not implicitly inherited. The cache is bounded and stores routing metadata, not resolved services or context-dependent support decisions. CompositeErrorListenerProvider traverses its children in declaration order, deduplicates by object identity, and logs and isolates a failing child (including deferred iteration failures). Attribute resolution failures are logged and skipped. Individual supports() checks remain in the reporter. Time and the event are created once, only when a listener supports the occurrence. For configuration-driven registration and automatic class discovery use componenta/error-handler-app.

`getAttribute()` distinguishes an absent default from explicit `null` via `Componenta\Error\Context\AttributeDefault::None`. The attribute map owns its top-level bindings; nested objects, streams and reference-bearing values are shared intentionally. Middleware requires downstream code to balance any changes to the process-wide PHP error-handler stack.

`ConsoleErrorRenderer` is a diagnostic renderer even with `debug: false`: it still exposes the exception message. Use `SafeCliRenderer` for untrusted output. Construct the diagnostic renderer directly; `create()` and `production()` are not part of the API. The `colors` constructor option accepts `null` (default: follow the current CLI output's decoration setting, or its error output for `ConsoleOutputInterface`, respecting `NO_COLOR`), `true` (force ANSI colors), or `false` (disable ANSI colors). Automatic detection is performed per render, not cached across contexts.

The standard HTTP response generator resolves the HTTP status once, checks renderer support using the enriched context, and uses SafeRenderer if the configured renderer declines. Renderer failures still follow the handler's emergency path. The CLI rendering handler delegates capability checks and rejects unsupported direct render calls. Representation digests are not copied from exceptions to newly rendered responses, and Vary wildcard semantics are preserved.

### Diagnostic limits

Diagnostic renderers are intended for trusted environments. Exception messages are bounded to 16 KiB before formatting. `JsonRenderer` includes at most 100 trace frames and reports the omitted count in `trace_omitted` when necessary. `PrettyPageRenderer` defaults to 100 displayed frames (including exception markers) and 10 exceptions, with hard ceilings of 256 frames and 32 exceptions even when the corresponding constructor limits are `null`. Exception property values, query parameters, parsed body and optional captured JSON share a fresh budget per rendering call (1,024 traversal nodes and 256 KiB of string data). Source excerpts have a separate budget of 32 reads and 256 KiB of source data. These are input-processing budgets, not a guarantee about the exact encoded HTML size. Truncation or omission of diagnostic data does not change the original occurrence. Use `SafeRenderer` or `SafeCliRenderer` in production.
