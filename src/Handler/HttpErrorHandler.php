<?php

declare(strict_types=1);

namespace Componenta\Error\Handler;

use Componenta\Error\ConfigKey;
use Componenta\Error\Context\ErrorContextAttribute;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorId\ErrorIdGeneratorInterface;
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
use Componenta\Error\Http\EmergencyHttpResponseGenerator;
use Componenta\Error\Internal\FailureLog;
use Componenta\Error\Internal\ErrorCorrelation;
use Componenta\Error\Reporter\ListenerExceptionPolicy;

final class HttpErrorHandler implements HttpErrorHandlerInterface
{
    public const string FALLBACK_GENERATOR = ConfigKey::HTTP_FALLBACK_GENERATOR;
    public const string RENDERER = ConfigKey::HTTP_RENDERER;
    public const string LISTENERS = ConfigKey::HTTP_LISTENERS;
    public const string GENERATORS = ConfigKey::HTTP_GENERATORS;

    /**
     * @var list<array{generator: HttpErrorResponseGeneratorInterface, priority: int}>
     */
    private array $generators = [];

    private bool $needsSort = false;

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
            emergencyGenerator: new EmergencyHttpResponseGenerator($responseFactory),
        );
    }

    public function __construct(
        private readonly HttpErrorResponseGeneratorInterface $fallbackGenerator,
        private readonly ErrorReporterInterface $reporter = new ErrorReporter(),
        private readonly ErrorIdGeneratorInterface $errorIdGenerator = new RandomErrorIdGenerator(),
        private readonly ?HttpErrorResponseGeneratorInterface $emergencyGenerator = null,
        private readonly ListenerExceptionPolicy $reporterFailurePolicy = ListenerExceptionPolicy::Swallow,
    ) {
    }

    public function addGenerator(HttpErrorResponseGeneratorInterface $generator, int $priority = 0): void
    {
        $this->generators[] = ['generator' => $generator, 'priority' => $priority];
        $this->needsSort = true;
    }

    public function supports(Throwable $exception, ErrorContextInterface $context): bool
    {
        return $context instanceof HttpErrorContextInterface;
    }

    public function handle(Throwable $exception, HttpErrorContextInterface $context): ResponseInterface
    {
        [$context, $errorId] = ErrorCorrelation::assign($exception, $context, $this->errorIdGenerator);

        $selected = null;
        try {
            $selected = $this->getGenerator($exception, $context);
            $response = $selected->generate($exception, $context);
        } catch (Throwable $failure) {
            FailureLog::write('HTTP response generation', $failure, $exception);
            $emergency = $this->emergencyGenerator
                ?? ($selected !== $this->fallbackGenerator ? $this->fallbackGenerator : null);
            try {
                if ($emergency === null || $emergency === $selected) {
                    throw $exception;
                }
                $response = $emergency->generate($exception, $context);
            } catch (Throwable $secondary) {
                FailureLog::write('emergency response', $secondary, $exception);
                try {
                    $this->reporter->report($exception, $context, errorId: $errorId);
                } catch (Throwable $reportingFailure) {
                    FailureLog::write('reporting', $reportingFailure, $exception);
                }
                throw $exception;
            }
        }

        try {
            $context = $context->withAttribute(ErrorContextAttribute::HTTP_STATUS_CODE, $response->getStatusCode());
            if ($errorId !== null) {
                $response = $response->withHeader('X-Error-ID', $errorId);
            }
        } catch (Throwable $failure) {
            FailureLog::write('HTTP error metadata', $failure, $exception);
        }

        try {
            $this->reporter->report($exception, $context, $errorId);
        } catch (Throwable $failure) {
            if ($this->reporterFailurePolicy === ListenerExceptionPolicy::Throw) {
                throw $failure;
            }
            FailureLog::write('reporting', $failure, $exception);
        }

        return $response;
    }

    private function getGenerator(
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
