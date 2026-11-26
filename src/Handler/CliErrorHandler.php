<?php

declare(strict_types=1);

namespace Componenta\Error\Handler;

use Componenta\Error\ConfigKey;
use Componenta\Error\Context\ErrorContextAttribute;
use Componenta\Error\Context\CliErrorContextInterface;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Reporter\ErrorReporterInterface;
use Componenta\Error\Renderer\SafeCliRenderer;
use Componenta\Error\Reporter\ErrorReporter;
use Throwable;
use Componenta\Error\Internal\FailureLog;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CliErrorHandler implements CliErrorHandlerInterface
{
    public const string FALLBACK_HANDLER = ConfigKey::CLI_FALLBACK_HANDLER;
    public const string RENDERER = ConfigKey::CLI_RENDERER;

    /**
     * @var list<array{handler: CliErrorHandlerInterface, priority: int}>
     */
    private array $handlers = [];

    private bool $needsSort = false;

    public static function createDefaults(
        ?ErrorRendererInterface $renderer = null,
        ?ErrorReporterInterface $reporter = null,
    ): self {
        return new self(
            fallbackHandler: new RenderingCliErrorHandler($renderer ?? new SafeCliRenderer()),
            reporter: $reporter ?? new ErrorReporter(),
        );
    }

    public function __construct(
        private readonly CliErrorHandlerInterface $fallbackHandler,
        private readonly ErrorReporterInterface $reporter = new ErrorReporter(),
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

    public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool
    {
        return $context instanceof CliErrorContextInterface;
    }

    public function handle(ErrorOccurrence $error, CliErrorContextInterface $context): void
    {
        $exception = $error->exception;

        $outputFailed = false;
        try {
            $this->getHandler($error, $context)->handle($error, $context);
        } catch (Throwable $failure) {
            FailureLog::write('CLI rendering', $failure, $exception);
            try {
                $output = $context->output;
                if ($output instanceof ConsoleOutputInterface) {
                    $output = $output->getErrorOutput();
                }
                $output->writeln('An unexpected error occurred' . "\nError ID: " . $error->id, OutputInterface::OUTPUT_RAW);
            } catch (Throwable $secondary) {
                FailureLog::write('emergency CLI output', $secondary, $exception);
                $outputFailed = true;
            }
        }

        try {
            $this->reporter->report($error, $context);
        } catch (Throwable $failure) {
            FailureLog::write('reporting', $failure, $exception);
        }

        if ($outputFailed) {
            throw $exception;
        }
    }

    private function getHandler(ErrorOccurrence $error, ErrorContextInterface $context): CliErrorHandlerInterface
    {
        $this->sortHandlers();

        foreach ($this->handlers as ['handler' => $handler]) {
            if ($handler->supports($error, $context)) {
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
