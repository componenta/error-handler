<?php

declare(strict_types=1);

namespace Componenta\Error\Internal;

use LogicException;
use Psr\Container\ContainerInterface;

/** @internal Validates untyped PSR-11 service boundaries. */
final class TypedService
{
    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    public static function get(ContainerInterface $container, string $id, string $type): object
    {
        $service = $container->get($id);
        if (!$service instanceof $type) {
            throw new LogicException(sprintf('Service "%s" must implement %s; got %s.', $id, $type, get_debug_type($service)));
        }

        return $service;
    }

    /**
     * @param array<array-key, mixed> $ids
     * @return list<string>
     */
    public static function ids(array $ids, string $key): array
    {
        $result = [];
        foreach ($ids as $id) {
            if (!is_string($id) || $id === '') {
                throw new LogicException(sprintf('Configuration "%s" must contain non-empty service identifiers.', $key));
            }
            $result[] = $id;
        }

        return $result;
    }
}
