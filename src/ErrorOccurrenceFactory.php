<?php

declare(strict_types=1);

namespace Componenta\Error;

use Componenta\Error\ErrorId\ErrorIdGeneratorInterface;
use Componenta\Error\ErrorId\RandomErrorIdGenerator;
use Componenta\Error\Internal\FailureLog;
use Throwable;

/** Creates an occurrence once at the boundary of the error handling system. */
final readonly class ErrorOccurrenceFactory
{
    public function __construct(
        private ErrorIdGeneratorInterface $generator = new RandomErrorIdGenerator(),
    ) {
    }

    public function create(Throwable $exception): ErrorOccurrence
    {
        try {
            return new ErrorOccurrence($exception, $this->generator->generate());
        } catch (Throwable $failure) {
            FailureLog::write('error ID generation', $failure, $exception);
        }

        // If the system randomness source also fails, let the entry point
        // choose its independent uncorrelated emergency path.
        return new ErrorOccurrence($exception, (new RandomErrorIdGenerator())->generate());
    }
}
