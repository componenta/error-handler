# Componenta Error Handler

Обработка ошибок HTTP и CLI для PHP 8.4+ приложений.

Пакет содержит переиспользуемый слой обработки ошибок. Он не выбирает конкретную реализацию PSR-7/PSR-17. HTTP-приложение должно зарегистрировать `Psr\Http\Message\ResponseFactoryInterface` через один из интеграционных пакетов `componenta/http-psr-*` или предоставить собственный `HttpErrorResponseGeneratorInterface`.

## Установка

```bash
composer require componenta/error-handler
```

Пакет объявляет `Componenta\Error\ConfigProvider` в `extra.componenta.config-providers`.
Если установлен `componenta/composer-plugin`, провайдер автоматически добавляется в сгенерированный список провайдеров.

## Связанные пакеты

| Пакет | Зачем нужен здесь |
|---|---|
| `componenta/http` | Содержит HTTP-исключения; интеграция с PSR-запросом и ответом реализуется HTTP-обработчиком ошибок и app-пакетами. |
| `componenta/pipeline` | Обычно ставит `ErrorHandlerMiddleware` самым внешним промежуточным обработчиком HTTP-конвейера. |
| `componenta/event` | Может использоваться для реакции на ошибки через слушателей. |
| `psr/log` | Подходит для логирования ошибок через слушателей отчёта. |
| `componenta/config` | Регистрирует стандартные HTTP/CLI обработчики, рендереры и сервис отчёта об ошибках. |
| `componenta/http-psr-nyholm`, `componenta/http-psr-diactoros`, `componenta/http-psr-guzzle` или `componenta/http-psr-slim` | Регистрирует конкретные PSR-17 фабрики для генерации HTTP-ответов. |

## Что предоставляет пакет

- HTTP и CLI обработчики ошибок.
- Резервный генератор ответа для исключений, которые не обработал специализированный генератор; для HTTP он всё равно требует явно зарегистрированную `ResponseFactoryInterface`.
- Рендереры ошибок для безопасного HTML/JSON, JSON, HTML, plain text, пустого вывода и составного рендера.
- Слушатели отчёта об ошибке для логирования, мониторинга и уведомлений.
- Иммутабельные HTTP и CLI контексты.
- PSR-15 промежуточный обработчик для HTTP-приложений.
- `ConfigProvider` для интеграции с фреймворком.

## HTTP

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

Специализированные генераторы регистрируются с приоритетом:

```php
$handler->addGenerator(new NotFoundGenerator($responseFactory), priority: 100);
$handler->addGenerator(new ValidationGenerator($responseFactory), priority: 50);
```

Генераторы с большим приоритетом проверяются раньше.

Собственный генератор реализует `HttpErrorResponseGeneratorInterface`:

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
        // Создайте и верните PSR-7 ответ.
    }
}
```

`HttpErrorResponseGenerator` один раз вычисляет HTTP-статус через `HttpStatusResolver` и записывает его в контекст как `ErrorContextAttribute::HTTP_STATUS_CODE` до рендера. Стандартный resolver использует типизированные HTTP-исключения (`HttpStatusAwareInterface` или исключения `componenta/http`), иначе возвращает 500. Числовой код исключения учитывается только при явной настройке `new HttpStatusResolver(useExceptionCode: true)`.

## CLI

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

## Рендереры

Встроенные рендереры:

- `SafeRenderer`
- `JsonRenderer`
- `HtmlRenderer`
- `PrettyPageRenderer`
- `SafeCliRenderer`
- `PlainTextRenderer`
- `ConsoleErrorRenderer`
- `NullRenderer`
- `CompositeErrorRenderer`

Резервный HTTP-генератор использует `SafeRenderer`, а CLI-обработчик использует `SafeCliRenderer`, если приложение не настроило другие сервисы.

Дополнительные интеграции рендереров находятся в отдельных пакетах:

| Пакет | Рендерер | Типичный сценарий |
|---|---|---|
| [`componenta/error-renderer-plates`](../error-renderer-plates/README.ru.md) | `PlatesRenderer` | Безопасные или собственные HTML-страницы ошибок через шаблоны League Plates. |
| [`componenta/error-renderer-whoops`](../error-renderer-whoops/README.ru.md) | `WhoopsRenderer` | Подробные страницы ошибок в разработке через Whoops. |
| [`componenta/error-renderer-symfony`](../error-renderer-symfony/README.ru.md) | `SymfonyRenderer` | Рендеринг страниц ошибок Symfony через `ErrorRendererInterface`. |
| [`componenta/error-renderer-ignition`](../error-renderer-ignition/README.ru.md) | `IgnitionRenderer` | Страницы ошибок Spatie Ignition для разработки. |
| [`componenta/error-renderer-collision`](../error-renderer-collision/README.ru.md) | `CollisionRenderer` | Насыщенный вывод ошибок в CLI через Collision. |

## Контексты

Контексты переносят HTTP-запрос или данные командной строки плюс произвольные атрибуты.

```php
$context = $context->withAttribute('user_id', 123);
$userId = $context->getAttribute('user_id');
```

Контексты иммутабельны: `withAttribute()` и `withAttributes()` возвращают новый экземпляр.

`HttpContext::fromGlobals()` не создаёт PSR-17 фабрики самостоятельно. Передайте `Nyholm\Psr7Server\ServerRequestCreatorInterface`, если контекст нужно собрать из PHP superglobals:

```php
$context = HttpContext::fromGlobals($serverRequestCreator);
```

## Отчёт об ошибках

Слушатели регистрируются для логирования или мониторинга:

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

Отдельные исключения можно исключить из отчётов по имени класса или predicate-функции.

`ErrorReporter` принимает `ListenerExceptionPolicy`. По умолчанию ошибки слушателей подавляются и пишутся через `error_log()`, поэтому ошибка логирования не заменит исходное исключение приложения.

## DI-регистрация

`ConfigProvider` регистрирует HTTP/CLI обработчики, рендереры, сервис отчёта об ошибках и зависимости промежуточного обработчика, нужные Componenta-приложениям.

Важные ключи конфигурации:

| Ключ | Назначение |
|---|---|
| `ConfigKey::HTTP_FALLBACK_GENERATOR` | Service id резервного `HttpErrorResponseGeneratorInterface`. |
| `ConfigKey::HTTP_RENDERER` | Рендерер стандартного HTTP-генератора ответа. |
| `ConfigKey::HTTP_GENERATORS` | Упорядоченные идентификаторы сервисов специализированных HTTP-генераторов ответа. |
| `ConfigKey::HTTP_LISTENERS` | HTTP-слушатели ошибок. |
| `ConfigKey::CLI_FALLBACK_HANDLER` | Резервный CLI-рендерер или обработчик. |
| `ConfigKey::CLI_RENDERER` | CLI-рендерер. |
| `ConfigKey::CLI_LISTENERS` | CLI-слушатели ошибок. |
| `ConfigKey::HTTP_REPORTER` / `CLI_REPORTER` | Независимые сервисы отчёта по типу транспорта. |
| `ConfigKey::ERROR_REPORTER` | Явное переопределение общим репортёром. |
| `ConfigKey::ERROR_ID_GENERATOR` | Сервис генератора идентификатора ошибки. |
| `ConfigKey::ERROR_LEVEL` | Уровень ошибок, который обрабатывает `ErrorHandlerMiddleware`. |

Если `ConfigKey::HTTP_FALLBACK_GENERATOR` не настроен, стандартный HTTP-обработчик требует `Psr\Http\Message\ResponseFactoryInterface` в контейнере.

## Переход на версию 2

Требуются Config 4 и PHP 8.4+. Регистрируйте слушателей в `ErrorListenerProvider`, фильтры исключений — в `ErrorReporter`, а не в обработчиках. Атрибуты контекста доступны только через контракт контекста. Все рендереры реализуют `ErrorRendererInterface::render()` и возвращают неизменяемый объект `RenderedError`, реализующий `Stringable`, с полями `content` и `contentType`. Рендерер определяет фактический тип содержимого, вызывающий код — способ его передачи. Числовой код исключения больше не определяет статус неявно. Обработчики изолируют ошибки репортёра и используют независимый аварийный генератор при наличии PSR-17 фабрики. ID ошибки содержит 128 бит случайной энтропии; HTTP-ответ передаёт ID в `X-Error-ID`. Отладочные рендереры могут раскрывать сообщения исключений и исходный код: в production используйте SafeRenderer. Не публикуйте HTML-журналы. Настраивайте обработчики и слушателей до выполнения запросов. Middleware восстанавливает состояние PHP-обработчика ошибок, но не перехватывает аварийное завершение процесса и не гарантирует изоляцию при пересекающемся выполнении Fiber.

## Проверки

`composer test` — unit-тесты Pest, `composer phpstan` — статический анализ, `composer benchmark` — PHPBench. Интеграция с DI 6 требует PHP 8.5: установите `composer require --dev 'componenta/di:^6.0@dev'` и выполните `composer test:integration`. CI также проверяет пять адаптеров рендеринга с текущей версией ядра.

Стандартный обработчик командной строки использует `SafeCliRenderer`: общее сообщение и идентификатор ошибки. Для диагностики явно выбирайте `PlainTextRenderer` или `ConsoleErrorRenderer`; они выводят исходные сообщения исключений и не предназначены для недоверенных получателей.

Рекурсивные вызовы одного `ErrorReporter` в том же контексте выполнения игнорируются, пока работают его слушатели или фильтры. Независимые Fiber могут отправлять отчёты одновременно.

## Результат рендеринга

```php
use Componenta\Error\Renderer\JsonRenderer;

$result = (new JsonRenderer())->render($exception, $context);
$result->content;      // Содержимое JSON
$result->contentType;  // application/json; charset=utf-8
(string) $result;     // То же содержимое
```

Пользовательские рендереры должны возвращать `new RenderedError($content, $contentType)`. `CompositeErrorRenderer` передаёт выбранный результат без изменений. Генератор HTTP-ответа использует содержимое и тип напрямую, обработчик командной строки выводит только содержимое. Отдельного контракта HTTP-рендеринга и неявной поддержки строковых рендереров нет.
