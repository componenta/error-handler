<?php

declare(strict_types=1);

namespace Componenta\Error\Http;

use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\Http\HttpStatusAwareInterface;
use Throwable;

final readonly class HttpStatusResolver implements HttpStatusResolverInterface
{
    public function __construct(
        private bool $useExceptionCode = true,
    ) {
    }

    public function resolve(Throwable $exception, HttpErrorContextInterface $context): int
    {
        if ($exception instanceof HttpStatusAwareInterface) {
            return $this->normalize($exception->statusCode);
        }

        if ($this->useExceptionCode) {
            $code = $exception->getCode();

            if ($code >= 400 && $code < 600) {
                return $code;
            }
        }

        return 500;
    }

    private function normalize(int $statusCode): int
    {
        return $statusCode >= 400 && $statusCode < 600 ? $statusCode : 500;
    }
}
