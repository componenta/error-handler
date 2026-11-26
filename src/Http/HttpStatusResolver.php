<?php

declare(strict_types=1);

namespace Componenta\Error\Http;

use Componenta\Error\Context\HttpErrorContextInterface;
use Componenta\Error\Http\HttpStatusAwareInterface;
use Throwable;

final readonly class HttpStatusResolver implements HttpStatusResolverInterface
{
    public function __construct(
        private bool $useExceptionCode = false,
    ) {
    }

    public function resolve(Throwable $exception, HttpErrorContextInterface $context): int
    {
        if ($exception instanceof HttpStatusAwareInterface || $exception instanceof \Componenta\Http\Exception\HttpException) {
            return $this->normalize($exception->statusCode);
        }

        if ($this->useExceptionCode) {
            $code = $exception->getCode();

            if (is_int($code) && $code >= 400 && $code < 600) {
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
