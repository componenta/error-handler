<?php

declare(strict_types=1);

namespace Componenta\Error\Internal;

use Componenta\Error\Context\ErrorContextAttribute;
use Componenta\Error\ErrorContextInterface;
use Componenta\Error\ErrorId\ErrorIdGeneratorInterface;
use Componenta\Error\ErrorId\RandomErrorIdGenerator;
use RuntimeException;
use Throwable;

/** @internal */
final class ErrorCorrelation
{
    public static function isValid(mixed $id): bool
    {
        return is_string($id) && preg_match('/\A[A-Za-z0-9._:-]{1,128}\z/', $id) === 1;
    }

    /**
     * @template T of ErrorContextInterface
     * @param T $context
     * @return array{T, ?string}
     */
    public static function assign(
        Throwable $exception,
        ErrorContextInterface $context,
        ErrorIdGeneratorInterface $generator,
    ): array {
        foreach ([$generator, new RandomErrorIdGenerator()] as $candidate) {
            try {
                $id = $candidate->generate($exception, $context);
                if (!self::isValid($id)) {
                    throw new RuntimeException('Invalid error identifier');
                }

                return [$context->withAttribute(ErrorContextAttribute::ERROR_ID, $id), $id];
            } catch (Throwable $failure) {
                FailureLog::write('error ID', $failure, $exception);
            }
        }

        try {
            $context = $context->withAttribute(ErrorContextAttribute::ERROR_ID, null);
        } catch (Throwable $failure) {
            FailureLog::write('error ID context', $failure, $exception);
        }

        return [$context, null];
    }

    public static function read(ErrorContextInterface $context): ?string
    {
        try {
            $id = $context->getAttribute(ErrorContextAttribute::ERROR_ID, null);
            return is_string($id) && self::isValid($id) ? $id : null;
        } catch (Throwable $failure) {
            FailureLog::write('reading error ID', $failure);
            return null;
        }
    }
}
