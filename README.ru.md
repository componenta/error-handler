# Componenta Error Handler

Обработка ошибок HTTP и CLI для PHP 8.4+ приложений.

Пакет содержит переиспользуемый слой обработки ошибок. Он не выбирает конкретную реализацию PSR-7/PSR-17. HTTP-приложение должно зарегистрировать `Psr\Http\Message\ResponseFactoryInterface` через один из интеграционных пакетов `componenta/http-psr-*` или предоставить собственный `HttpErrorResponseGeneratorInterface`.

## Установка

```bash
composer require componenta/error-handler
```


## Связанные пакеты

| Пакет | Зачем нужен здесь |
|---|---|
| `componenta/http` | Содержит HTTP-исключения; интеграция с PSR-запросом и ответом реализуется HTTP-обработчиком ошибок и app-пакетами. |
| `componenta/pipeline` | Обычно ставит `ErrorHandlerMiddleware` самым внешним промежуточным обработчиком HTTP-конвейера. |
| `componenta/event` | Может использоваться для реакции на ошибки через слушателей. |
| `psr/log` | Подходит для логирования ошибок через слушателей отчёта. |
| `componenta/http-psr-nyholm`, `componenta/http-psr-diactoros`, `componenta/http-psr-guzzle` или `componenta/http-psr-slim` | Регистрирует конкретные PSR-17 фабрики для генерации HTTP-ответов. |

## Что предоставляет пакет

- HTTP и CLI обработчики ошибок.
- Резервный генератор ответа для исключений, которые не обработал специализированный генератор; для HTTP он всё равно требует явно зарегистрированную `ResponseFactoryInterface`.
- Рендереры ошибок для безопасного HTML/JSON, JSON, HTML, plain text, пустого вывода и составного рендера.
- Слушатели отчёта об ошибке для логирования, мониторинга и уведомлений.
- Иммутабельные HTTP и CLI контексты.
- PSR-15 промежуточный обработчик для HTTP-приложений.

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
        // Создайте и верните PSR-7 ответ.
    }
}
```

`HttpErrorResponseGenerator` один раз вычисляет HTTP-статус через `HttpStatusResolver` и записывает его в контекст как `ErrorContextAttribute::HTTP_STATUS_CODE` до рендера. Стандартный resolver использует типизированные HTTP-исключения (`HttpStatusAwareInterface` или исключения `componenta/http`), иначе возвращает 500. Числовой код исключения учитывается только при явной настройке `new HttpStatusResolver(useExceptionCode: true)`.

## CLI

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

Репортёр не зависит от транспорта. Список слушателей и политика исключений задаются при создании:

```php
use Componenta\Error\Event\ErrorEventInterface;
use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\Provider\ArrayErrorListenerProvider;
use Componenta\Error\Reporter\ErrorReporter;
use Componenta\Error\Reporter\Policy\NonReportableErrors;

$listener = ErrorListener::createFrom(static function (ErrorEventInterface $event): void {
    // Используйте $event->error->id, $event->error->exception, $event->context и $event->timestamp.
});
$reporter = new ErrorReporter(
    new ArrayErrorListenerProvider([Throwable::class => [['listener' => $listener, 'priority' => 100]]]),
    new NonReportableErrors([\InvalidArgumentException::class]),
);
$handler = HttpErrorHandler::createDefaults($responseFactory, reporter: $reporter);
```

Глобальное исключение запрещает передачу события всем слушателям. Предикат отдельного слушателя ограничивает только соответствующее направление регистрации. Правила принимают имена классов исключений, списки имён классов и вызываемые объекты, принимающие (ErrorOccurrence, ErrorContextInterface) и возвращающие bool. Для предиката исключения true означает исключить, для предиката слушателя — обработать. Слушатели упорядочиваются по убыванию приоритета, при равных приоритетах сохраняется порядок объявления, при повторении экземпляра действует наибольший из совпавших приоритетов. Провайдер и политика неизменяемы после создания.

По умолчанию репортёр продолжает регистрацию при сбое политики, использует системные часы UTC при отказе внедрённых часов и изолирует ошибки отдельных слушателей. ListenerExceptionPolicy::Throw — явная настройка при непосредственном использовании репортёра; HTTP- и CLI-обработчики всегда защищают исходную ошибку от сбоев регистрации. Рекурсивная отправка в том же контексте выполнения подавляется; независимые Fiber работают отдельно.

### Исключения, не подлежащие регистрации

```php
use Componenta\Error\Reporter\Attribute\NonReportable;

#[NonReportable]
final class ExpectedException extends \RuntimeException
{
}
```

Стандартная политика `NonReportableErrors` исключает непосредственно помеченные классы до проверки настроенных правил, обращения к провайдеру слушателей и получения времени. Атрибут не имеет параметров, не повторяется и не наследуется неявно. Настроенные правила по имени класса по-прежнему учитывают наследников. Атрибут не влияет на рендеринг, HTTP-статус, вывод CLI и идентификатор ошибки. Собственная реализация `ErrorReportingPolicyInterface` может определять иные правила. Используйте атрибут для исключений, которые по своему назначению не подлежат регистрации; решения, зависящие от приложения, задавайте через конфигурацию или предикаты. Не помечайте исключения, которые необходимо регистрировать для аудита безопасности или эксплуатации.

## Интеграция с фреймворком

Установите `componenta/error-handler-app` для провайдера конфигурации, фабрик сервисов и автоматического обнаружения классов Componenta. Самостоятельное ядро не зависит от Config или DI. При использовании вне фреймворка создавайте обработчики и репортёр напрямую.

## Переход на версию 2

Требуется PHP 8.4+. Передавайте слушателей в конструктор `ArrayErrorListenerProvider`, а правила исключения — в конструктор `NonReportableErrors`. Атрибуты контекста доступны только через контракт контекста. Все рендереры реализуют `ErrorRendererInterface::render()` и возвращают неизменяемый объект `RenderedError`, реализующий `Stringable`, с полями `content` и `contentType`. Рендерер определяет фактический тип содержимого, вызывающий код — способ его передачи. Числовой код исключения больше не определяет статус неявно. Обработчики изолируют ошибки репортёра и используют независимый аварийный генератор при наличии PSR-17 фабрики. ID ошибки содержит 128 бит случайной энтропии; HTTP-ответ передаёт ID в `X-Error-ID`. Отладочные рендереры могут раскрывать сообщения исключений и исходный код: в production используйте SafeRenderer. Не публикуйте HTML-журналы. Настраивайте обработчики и слушателей до выполнения запросов. Middleware восстанавливает состояние PHP-обработчика ошибок, но не перехватывает аварийное завершение процесса и не гарантирует изоляцию при пересекающемся выполнении Fiber.

## Проверки

`composer test` — unit-тесты Pest, `composer phpstan` — статический анализ, `composer benchmark` — PHPBench. `composer test:integration` проверяет самостоятельную сборку компонентов; интеграция с Config 4 и DI 6 проверяется в error-handler-app. CI также проверяет пять адаптеров рендеринга с текущей версией ядра.

Стандартный обработчик командной строки использует `SafeCliRenderer`: общее сообщение и идентификатор ошибки. Для диагностики явно выбирайте `PlainTextRenderer` или `ConsoleErrorRenderer`; они выводят исходные сообщения исключений и не предназначены для недоверенных получателей.

Рекурсивные вызовы одного `ErrorReporter` в том же контексте выполнения игнорируются, пока работают его слушатели или фильтры. Независимые Fiber могут отправлять отчёты одновременно.

## Результат рендеринга

```php
use Componenta\Error\Renderer\JsonRenderer;

$error = (new \Componenta\Error\ErrorOccurrenceFactory())->create($exception);
$result = (new JsonRenderer())->render($error, $context);
$result->content;      // Содержимое JSON
$result->contentType;  // application/json; charset=utf-8
(string) $result;     // То же содержимое
```

Пользовательские рендереры должны возвращать `new RenderedError($content, $contentType)`. `CompositeErrorRenderer` передаёт выбранный результат без изменений. Генератор HTTP-ответа использует содержимое и тип напрямую, обработчик командной строки выводит только содержимое. Отдельного контракта HTTP-рендеринга и неявной поддержки строковых рендереров нет.

`ErrorReporter` устанавливает временную метку через внедрённый `ClockInterface` (PSR-20); по умолчанию используются системные часы UTC. Событие хранит готовую временную метку. Результат транспортного слоя в событие не передаётся; фактический HTTP-статус, если он известен, доступен через `ErrorContextAttribute::HTTP_STATUS_CODE`.

`ErrorOccurrence` содержит исходное `Throwable` и обязательный идентификатор. `ErrorOccurrenceFactory` создаёт объект один раз на границе перехвата; стандартный ID состоит из `ERR-` и 128 случайных бит в шестнадцатеричном представлении (без суффикса времени). Контракты рендеринга, обработки и регистрации принимают тот же экземпляр. `HttpStatusResolver` принимает исходное исключение. ID не хранится в атрибутах контекста. Конфигурация error-handler-app использует общий `ErrorReporterInterface` для HTTP и CLI; переопределяйте сервис соответствующего интерфейса для замены репортёра, политики, провайдера слушателей или генератора ID. `ErrorEvent` содержит только `error`, `context` и `timestamp`.

## Провайдеры слушателей

Все провайдеры предоставляют только provideFor(Throwable): iterable<ErrorListenerInterface>. ArrayErrorListenerProvider принимает карту типов Throwable и списков экземпляров слушателей либо записей ['listener' => экземпляр, 'priority' => int]. Throwable::class соответствует всем исключениям. Учитываются родительские классы и совместимые интерфейсы. AttributeErrorListenerProvider один раз индексирует #[ListenTo(ExceptionType::class, priority: 10)] на переданных конкретных классах слушателей, вызывая переданный Closure-резолвер только для совпавших классов. При нескольких совпавших атрибутах действует наибольший приоритет. Атрибуты не наследуются неявно. Ограниченный кеш содержит метаданные маршрутизации, а не экземпляры разрешённых сервисов или результаты проверки контекста. CompositeErrorListenerProvider обходит вложенные провайдеры в порядке объявления, устраняет дубликаты по идентичности экземпляров и изолирует сбои вложенных источников, в том числе ошибки отложенной итерации. Сбой разрешения слушателя регистрируется и не мешает остальным. Проверка supports() остаётся в репортёре. Событие и время создаются один раз, только при наличии поддерживающего слушателя. Конфигурирование и автоматическое обнаружение классов обеспечивает componenta/error-handler-app.

`getAttribute()` отличает отсутствие значения по умолчанию от явно переданного `null` через `Componenta\Error\Context\AttributeDefault::None`. Карта атрибутов владеет верхнеуровневыми привязками; вложенные объекты, потоки и значения с PHP-ссылками намеренно разделяются. Код ниже middleware должен сбалансированно восстанавливать собственные изменения глобального стека обработчиков PHP.
