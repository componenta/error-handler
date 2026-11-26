<?php

declare(strict_types=1);

namespace Componenta\Error\Handler;

use Componenta\Error\Context\ErrorContextAttribute;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorOccurrence;
use Componenta\Error\Renderer\ErrorRendererInterface;
use Componenta\Error\Reporter\ErrorReporterInterface;
use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\Http\HttpErrorResponseGeneratorInterface;
use Componenta\Error\Http\HttpErrorResponseGenerator;
use Componenta\Error\Reporter\ErrorReporter;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Componenta\Error\Http\EmergencyHttpResponseGenerator;
use Componenta\Error\Internal\FailureLog;

final class HttpErrorHandler implements HttpErrorHandlerInterface
{

    /**
     * @var list<array{generator: HttpErrorResponseGeneratorInterface, priority: int}>
     */
    private array $generators = [];

    private bool $needsSort = false;

    public static function createDefaults(
        ResponseFactoryInterface $responseFactory,
        ?ErrorRendererInterface $renderer = null,
        ?ErrorReporterInterface $reporter = null,
    ): self {
        $generator = new HttpErrorResponseGenerator(
            responseFactory: $responseFactory,
            renderer: $renderer ?? new \Componenta\Error\Renderer\SafeRenderer(),
        );

        return new self(
            fallbackGenerator: $generator,
            reporter: $reporter ?? new ErrorReporter(),
            emergencyGenerator: new EmergencyHttpResponseGenerator($responseFactory),
        );
    }

    public function __construct(
        private readonly HttpErrorResponseGeneratorInterface $fallbackGenerator,
        private readonly ErrorReporterInterface $reporter = new ErrorReporter(),
        private readonly ?HttpErrorResponseGeneratorInterface $emergencyGenerator = null,
    ) {
    }

    public function addGenerator(HttpErrorResponseGeneratorInterface $generator, int $priority = 0): void
    {
        $this->generators[] = ['generator' => $generator, 'priority' => $priority];
        $this->needsSort = true;
    }

    public function supports(ErrorOccurrence $error, ErrorContextInterface $context): bool
    {
        return $context instanceof HttpErrorContextInterface;
    }

    public function handle(ErrorOccurrence $error, HttpErrorContextInterface $context): ResponseInterface
    {
        $exception = $error->exception;

        $selected = null;
        try {
            $selected = $this->getGenerator($error, $context);
            $response = $selected->generate($error, $context);
        } catch (Throwable $failure) {
            FailureLog::write('HTTP response generation', $failure, $exception);
            $emergency = $this->emergencyGenerator
                ?? ($selected !== $this->fallbackGenerator ? $this->fallbackGenerator : null);
            try {
                if ($emergency === null || $emergency === $selected) {
                    throw $exception;
                }
                $response = $emergency->generate($error, $context);
            } catch (Throwable $secondary) {
                FailureLog::write('emergency response', $secondary, $exception);
                try {
                    $this->reporter->report($error, $context);
                } catch (Throwable $reportingFailure) {
                    FailureLog::write('reporting', $reportingFailure, $exception);
                }
                throw $exception;
            }
        }

        try {
            $context = $context->withAttribute(ErrorContextAttribute::HTTP_STATUS_CODE, $response->getStatusCode());
        } catch (Throwable $failure) {
            FailureLog::write('HTTP error context metadata', $failure, $exception);
        }

        try {
            $response = $response->withHeader('X-Error-ID', $error->id);
        } catch (Throwable $failure) {
            FailureLog::write('HTTP error correlation header', $failure, $exception);
        }

        try {
            $this->reporter->report($error, $context);
        } catch (Throwable $failure) {
            FailureLog::write('reporting', $failure, $exception);
        }

        return $response;
    }

    private function getGenerator(
        ErrorOccurrence $error,
        ErrorContextInterface $context,
    ): HttpErrorResponseGeneratorInterface {
        $this->sortGenerators();

        foreach ($this->generators as ['generator' => $generator]) {
            if ($generator->supports($error, $context)) {
                return $generator;
            }
        }

        return $this->fallbackGenerator;
    }

    private function sortGenerators(): void
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
