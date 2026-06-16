<?php

declare(strict_types=1);

namespace Componenta\Error\Handler;

use Componenta\Error\ConfigKey;
use Componenta\Error\Context\ErrorContextAttribute;
use Componenta\Error\Context\CliErrorContextInterface;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorId\ErrorIdGeneratorInterface;
use Componenta\Error\Event\ErrorListenerInterface;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Reporter\ErrorReporterInterface;
use Componenta\Error\ErrorId\RandomErrorIdGenerator;
use Componenta\Error\Renderer\PlainTextRenderer;
use Componenta\Error\Reporter\ErrorReporter;
use Throwable;

class CliErrorHandler implements CliErrorHandlerInterface
{
    public const string FALLBACK_HANDLER = ConfigKey::CLI_FALLBACK_HANDLER;
    public const string RENDERER = ConfigKey::CLI_RENDERER;
    public const string LISTENERS = ConfigKey::CLI_LISTENERS;

    /**
     * @var list<array{handler: CliErrorHandlerInterface, priority: int}>
     */
    protected array $handlers = [];

    protected bool $needsSort = false;

    public static function createDefaults(
        ?ErrorRendererInterface $renderer = null,
        ?ErrorReporterInterface $reporter = null,
        ?ErrorIdGeneratorInterface $errorIdGenerator = null,
    ): self {
        return new self(
            fallbackHandler: new RenderingCliErrorHandler($renderer ?? new PlainTextRenderer()),
            reporter: $reporter ?? new ErrorReporter(),
            errorIdGenerator: $errorIdGenerator ?? new RandomErrorIdGenerator(),
        );
    }

    public function __construct(
        protected CliErrorHandlerInterface $fallbackHandler,
        protected ErrorReporterInterface $reporter = new ErrorReporter(),
        protected ErrorIdGeneratorInterface $errorIdGenerator = new RandomErrorIdGenerator(),
    ) {
    }

    public function addHandler(CliErrorHandlerInterface $handler, int $priority = 0): void
    {
        $this->handlers[] = ['handler' => $handler, 'priority' => $priority];
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
        return $context instanceof CliErrorContextInterface;
    }

    public function handle(Throwable $exception, CliErrorContextInterface $context): void
    {
        $errorId = $this->errorIdGenerator->generate($exception, $context);
        $context = $context->withAttribute(ErrorContextAttribute::ERROR_ID, $errorId);

        $this->getHandler($exception, $context)->handle($exception, $context);
        $this->reporter->report($exception, $context, errorId: $errorId);
    }

    protected function getHandler(Throwable $exception, ErrorContextInterface $context): CliErrorHandlerInterface
    {
        $this->sortHandlers();

        foreach ($this->handlers as ['handler' => $handler]) {
            if ($handler->supports($exception, $context)) {
                return $handler;
            }
        }

        return $this->fallbackHandler;
    }

    protected function sortHandlers(): void
    {
        if ($this->needsSort) {
            usort(
                $this->handlers,
                static fn(array $a, array $b): int => $b['priority'] <=> $a['priority'],
            );
            $this->needsSort = false;
        }
    }
}
