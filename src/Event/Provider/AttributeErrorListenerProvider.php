<?php

declare(strict_types=1);

namespace Componenta\Error\Event\Provider;

use Closure;
use Componenta\Error\Event\Attribute\ListenTo;
use Componenta\Error\Event\ErrorListenerInterface;
use Componenta\Error\Event\ErrorListenerProviderInterface;
use Componenta\Error\Event\Provider\Internal\ListenerIndex;
use Componenta\Error\Internal\FailureLog;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;
use UnexpectedValueException;

/**
 * Indexes explicitly supplied listener classes once and resolves matching services
 * on demand. No class discovery or framework container dependency.
 */
final readonly class AttributeErrorListenerProvider implements ErrorListenerProviderInterface
{
    /** @var ListenerIndex<class-string<ErrorListenerInterface>> */
    private ListenerIndex $index;

    /** @var Closure(class-string<ErrorListenerInterface>): ErrorListenerInterface */
    private Closure $resolver;

    /**
     * @param iterable<mixed> $classes Concrete error listener class names.
     * @param Closure(class-string<ErrorListenerInterface>): ErrorListenerInterface $resolver
     */
    public function __construct(iterable $classes, Closure $resolver)
    {
        $this->resolver = $resolver;
        $registrations = [];
        $seen = [];
        foreach ($classes as $class) {
            if (!is_string($class) || !is_a($class, ErrorListenerInterface::class, true)) {
                throw new InvalidArgumentException('Expected an error listener class.');
            }

            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isEnum()) {
                throw new InvalidArgumentException('Error listener class must be concrete.');
            }
            $canonical = $reflection->getName();
            $key = strtolower($canonical);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            // Class attributes are intentionally not inherited implicitly.
            foreach ($reflection->getAttributes(ListenTo::class) as $attribute) {
                $route = $attribute->newInstance();
                $registrations[] = [
                    'exception' => $route->exception,
                    'value' => $canonical,
                    'priority' => $route->priority,
                ];
            }
        }

        $this->index = new ListenerIndex($registrations);
    }

    public function provideFor(Throwable $exception): iterable
    {
        $seen = [];
        foreach ($this->index->matching($exception) as $class) {

            try {
                // Validate the resolver boundary, even when a caller bypasses PHPDoc types.
                $listener = ($this->resolver)($class);
                if (!$listener instanceof $class) {
                    throw new UnexpectedValueException('Resolver must return an instance of the registered listener class.');
                }
            } catch (Throwable $failure) {
                FailureLog::write('resolving error listener', $failure, $exception);
                continue;
            }

            if (!isset($seen[spl_object_id($listener)])) {
                $seen[spl_object_id($listener)] = $listener;
                yield $listener;
            }
        }
    }
}
