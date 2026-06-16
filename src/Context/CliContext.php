<?php

declare(strict_types=1);

namespace Componenta\Error\Context;

use Componenta\Error\Context\CliErrorContextInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI error context implementation
 *
 * Provides console I/O context for error handling.
 * Can be created from Symfony Console components or from $argv.
 */
final readonly class CliContext extends AbstractErrorContext implements CliErrorContextInterface
{
    /**
     * Create CLI error context
     *
     * @param InputInterface $input Console input
     * @param OutputInterface $output Console output
     * @param array<string|int, mixed> $attributes Additional context attributes
     */
    public function __construct(
        public InputInterface $input,
        public OutputInterface $output,
        array $attributes = [],
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create context from $argv
     *
     * Creates ArgvInput from command line arguments and
     * ConsoleOutput for terminal output.
     *
     * @param array<string|int, mixed> $attributes Additional context attributes
     * @return self New context instance
     */
    public static function fromArgv(array $attributes = []): self
    {
        return new self(
            new ArgvInput(),
            new ConsoleOutput(),
            $attributes,
        );
    }

    /**
     * Create new context with additional attributes
     *
     * @param array<string|int, mixed> $attributes Attributes to add
     * @return static New instance with merged attributes
     */
    public function withAttributes(array $attributes): static
    {
        return new self(
            $this->input,
            $this->output,
            array_merge($this->attributes, $attributes),
        );
    }
}
