<?php

declare(strict_types=1);

namespace Componenta\Error\Handler;

use Componenta\Error\ConfigKey;
use Componenta\Error\Context\ErrorContextAttribute;
use Componenta\Error\Context\CliErrorContextInterface;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorId\ErrorIdGeneratorInterface;
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

final class CliErrorHandler implements CliErrorHandlerInterface
{
    public const string FALLBACK_HANDLER = ConfigKey::CLI_FALLBACK_HANDLER;
    public const string RENDERER = ConfigKey::CLI_RENDERER;
    public const string LISTENERS = ConfigKey::CLI_LISTENERS;

    /**
     * @var list<array{handler: CliErrorHandlerInterface, priority: int}>
     */
    private array $handlers = [];

    private bool $needsSort = false;

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
        private readonly CliErrorHandlerInterface $fallbackHandler,
        private readonly ErrorReporterInterface $reporter = new ErrorReporter(),
        private readonly ErrorIdGeneratorInterface $errorIdGenerator = new RandomErrorIdGenerator(),
        private readonly ListenerExceptionPolicy $reporterFailurePolicy = ListenerExceptionPolicy::Swallow,
    ) {
        if ($fallbackHandler instanceof self) {
            throw new \InvalidArgumentException('A CLI aggregate cannot be used as a fallback delegate.');
        }
    }

    public function addHandler(CliErrorHandlerInterface $handler, int $priority = 0): void
    {
        if ($handler instanceof self) {
            throw new \InvalidArgumentException('A CLI aggregate cannot be registered as its own delegate.');
        }
        $this->handlers[] = ['handler' => $handler, 'priority' => $priority];
        $this->needsSort = true;
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

    private function getHandler(Throwable $exception, ErrorContextInterface $context): CliErrorHandlerInterface
    {
        $this->sortHandlers();

        foreach ($this->handlers as ['handler' => $handler]) {
            if ($handler->supports($exception, $context)) {
                return $handler;
            }
        }

        return $this->fallbackHandler;
    }

    private function sortHandlers(): void
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
