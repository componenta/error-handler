<?php

declare(strict_types=1);

namespace Componenta\Error\Context;

use Componenta\Error\ErrorContextInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI-specific error context interface
 *
 * Extends base context with console I/O information.
 */
interface CliErrorContextInterface extends ErrorContextInterface
{
    /**
     * Get console input
     */
    public InputInterface $input { get; }

    /**
     * Get console output
     */
    public OutputInterface $output { get; }
}
