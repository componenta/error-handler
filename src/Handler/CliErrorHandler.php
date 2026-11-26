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
use Componenta\Error\Internal\FailureLog;
use Componenta\Error\Reporter\ListenerExceptionPolicy;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
        private readonly ListenerExceptionPolicy $reporterFailurePolicy = ListenerExceptionPolicy::Swallow,
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
        $errorId = null;
        try {
            $id = $this->errorIdGenerator->generate($exception, $context);
            $context = $context->withAttribute(ErrorContextAttribute::ERROR_ID, $id);
            $errorId = $id;
        } catch (Throwable $failure) {
            FailureLog::write('error ID', $failure, $exception);
        }

        $outputFailed = false;
        try {
            $this->getHandler($exception, $context)->handle($exception, $context);
        } catch (Throwable $failure) {
            FailureLog::write('CLI rendering', $failure, $exception);
            try {
                $output = $context->output;
                if ($output instanceof ConsoleOutputInterface) {
                    $output = $output->getErrorOutput();
                }
                $output->writeln('An unexpected error occurred', OutputInterface::OUTPUT_RAW);
            } catch (Throwable $secondary) {
                FailureLog::write('emergency CLI output', $secondary, $exception);
                $outputFailed = true;
            }
        }

        try {
            $this->reporter->report($exception, $context, errorId: $errorId);
        } catch (Throwable $failure) {
            if (!$outputFailed && $this->reporterFailurePolicy === ListenerExceptionPolicy::Throw) {
                throw $failure;
            }
            FailureLog::write('reporting', $failure, $exception);
        }

        if ($outputFailed) {
            throw $exception;
        }
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
