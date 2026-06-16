<?php

declare(strict_types=1);

namespace Componenta\Error\Handler;

use Componenta\Error\ConfigKey;
use Componenta\Error\Context\ErrorContextAttribute;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorId\ErrorIdGeneratorInterface;
use Componenta\Error\Event\ErrorListenerInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Reporter\ErrorReporterInterface;
use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\Http\HttpErrorResponseGeneratorInterface;
use Componenta\Error\ErrorId\RandomErrorIdGenerator;
use Componenta\Error\Http\HttpErrorResponseGenerator;
use Componenta\Error\Reporter\ErrorReporter;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class HttpErrorHandler implements HttpErrorHandlerInterface
{
    public const string FALLBACK_GENERATOR = ConfigKey::HTTP_FALLBACK_GENERATOR;
    public const string RENDERER = ConfigKey::HTTP_RENDERER;
    public const string LISTENERS = ConfigKey::HTTP_LISTENERS;
    public const string GENERATORS = ConfigKey::HTTP_GENERATORS;

    /**
     * @var list<array{generator: HttpErrorResponseGeneratorInterface, priority: int}>
     */
    protected array $generators = [];

    protected bool $needsSort = false;

    public static function createDefaults(
        ResponseFactoryInterface $responseFactory,
        ?ErrorRendererInterface $renderer = null,
        ?ErrorReporterInterface $reporter = null,
        ?ErrorIdGeneratorInterface $errorIdGenerator = null,
    ): self {
        $generator = new HttpErrorResponseGenerator(
            responseFactory: $responseFactory,
            renderer: $renderer ?? new \Componenta\Error\Renderer\SafeRenderer(),
        );

        return new self(
            fallbackGenerator: $generator,
            reporter: $reporter ?? new ErrorReporter(),
            errorIdGenerator: $errorIdGenerator ?? new RandomErrorIdGenerator(),
        );
    }

    public function __construct(
        protected HttpErrorResponseGeneratorInterface $fallbackGenerator,
        protected ErrorReporterInterface $reporter = new ErrorReporter(),
        protected ErrorIdGeneratorInterface $errorIdGenerator = new RandomErrorIdGenerator(),
    ) {
    }

    public function addGenerator(HttpErrorResponseGeneratorInterface $generator, int $priority = 0): void
    {
        $this->generators[] = ['generator' => $generator, 'priority' => $priority];
        $this->needsSort = true;
    }

    public function addListener(ErrorListenerInterface $listener, int $priority = 0): void
    {
        if (!$this->reporter instanceof ErrorReporter) {
            throw new \LogicException('Listeners can only be added to the default error reporter.');
        }

        $this->reporter->addListener($listener, $priority);
    }

    /**
     * @param class-string<Throwable>|list<class-string<Throwable>>|callable(Throwable, ErrorContextInterface): bool $filter
     */
    public function addNonReportableError(string|array|callable $filter): void
    {
        if (!$this->reporter instanceof ErrorReporter) {
            throw new \LogicException('Non-reportable filters can only be added to the default error reporter.');
        }

        $this->reporter->addNonReportableError($filter);
    }

    public function supports(Throwable $exception, ErrorContextInterface $context): bool
    {
        return $context instanceof HttpErrorContextInterface;
    }

    public function handle(Throwable $exception, HttpErrorContextInterface $context): ResponseInterface
    {
        $errorId = $this->errorIdGenerator->generate($exception, $context);
        $context = $context->withAttribute(ErrorContextAttribute::ERROR_ID, $errorId);
        $response = $this->getGenerator($exception, $context)->generate($exception, $context);

        $this->reporter->report($exception, $context, $response, $errorId);

        return $response;
    }

    protected function getGenerator(
        Throwable $exception,
        ErrorContextInterface $context,
    ): HttpErrorResponseGeneratorInterface {
        $this->sortGenerators();

        foreach ($this->generators as ['generator' => $generator]) {
            if ($generator->supports($exception, $context)) {
                return $generator;
            }
        }

        return $this->fallbackGenerator;
    }

    protected function sortGenerators(): void
    {
        if ($this->needsSort) {
            usort(
                $this->generators,
                static fn(array $a, array $b): int => $b['priority'] <=> $a['priority'],
            );
            $this->needsSort = false;
        }
    }
}
