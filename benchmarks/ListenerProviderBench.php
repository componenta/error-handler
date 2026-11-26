<?php

declare(strict_types=1);

namespace Componenta\Error\Benchmarks;

use Componenta\Error\Event\ErrorListener;
use Componenta\Error\Event\ErrorListenerInterface;
use Componenta\Error\Event\Provider\ArrayErrorListenerProvider;
use Componenta\Error\Event\Provider\AttributeErrorListenerProvider;
use Componenta\Error\Event\Provider\CompositeErrorListenerProvider;
use Componenta\Error\Tests\Fixture\BenchmarkAttributedListener;
use PhpBench\Attributes as Bench;
use RuntimeException;
use Throwable;

#[Bench\BeforeMethods('setUp')]
#[Bench\Revs(100)]
#[Bench\Iterations(5)]
final class ListenerProviderBench
{
    private array $registrations;
    private Throwable $exception;
    private Throwable $unmatched;
    private ArrayErrorListenerProvider $array;
    private ArrayErrorListenerProvider $coldArray;
    private AttributeErrorListenerProvider $coldAttribute;
    private AttributeErrorListenerProvider $attribute;
    private CompositeErrorListenerProvider $composite;
    private BenchmarkAttributedListener $listener;

    public function setUp(): void
    {
        $this->registrations = [RuntimeException::class => [], \LogicException::class => []];
        for ($i = 0; $i < 10; ++$i) {
            $listener = ErrorListener::createFrom(static function (): void {});
            // Two matching registrations for one instance.
            $this->registrations[RuntimeException::class][] = $listener;
            $this->registrations[RuntimeException::class][] = ['listener' => $listener, 'priority' => $i];
        }
        for ($i = 0; $i < 1000; ++$i) {
            $this->registrations[\LogicException::class][] = ErrorListener::createFrom(static function (): void {});
        }

        $this->exception = new RuntimeException('provider benchmark');
        $this->unmatched = new \TypeError('provider miss');
        $this->array = new ArrayErrorListenerProvider($this->registrations);
        $this->listener = new BenchmarkAttributedListener();
        $this->attribute = new AttributeErrorListenerProvider(
            [BenchmarkAttributedListener::class],
            fn (): ErrorListenerInterface => $this->listener,
        );
        $this->composite = new CompositeErrorListenerProvider([$this->array, $this->attribute]);
        $this->coldArray = new ArrayErrorListenerProvider($this->registrations);
        $this->coldAttribute = new AttributeErrorListenerProvider(
            [BenchmarkAttributedListener::class],
            fn (): ErrorListenerInterface => $this->listener,
        );
        // Prepare cache entries outside the measured warm operations.
        iterator_to_array($this->array->provideFor($this->exception));
        iterator_to_array($this->array->provideFor($this->unmatched));
        iterator_to_array($this->attribute->provideFor($this->exception));
    }

    public function benchArrayConstructionAndLookup(): void
    {
        iterator_to_array((new ArrayErrorListenerProvider($this->registrations))->provideFor($this->exception));
    }

    public function benchArrayConstruction(): void
    {
        new ArrayErrorListenerProvider($this->registrations);
    }

    #[Bench\Revs(1)]
    #[Bench\Iterations(10)]
    public function benchArrayFirstLookup(): void
    {
        iterator_to_array($this->coldArray->provideFor($this->exception));
    }

    public function benchArrayWarm(): void
    {
        iterator_to_array($this->array->provideFor($this->exception));
    }

    public function benchArrayWarmMiss(): void
    {
        iterator_to_array($this->array->provideFor($this->unmatched));
    }

    public function benchAttributeConstructionAndLookup(): void
    {
        $provider = new AttributeErrorListenerProvider([BenchmarkAttributedListener::class], fn (): ErrorListenerInterface => $this->listener);
        iterator_to_array($provider->provideFor($this->exception));
    }

    public function benchAttributeConstruction(): void
    {
        new AttributeErrorListenerProvider([BenchmarkAttributedListener::class], fn (): ErrorListenerInterface => $this->listener);
    }

    #[Bench\Revs(1)]
    #[Bench\Iterations(10)]
    public function benchAttributeFirstLookup(): void
    {
        iterator_to_array($this->coldAttribute->provideFor($this->exception));
    }

    public function benchAttributeWarm(): void
    {
        iterator_to_array($this->attribute->provideFor($this->exception));
    }

    public function benchCompositeWarm(): void
    {
        iterator_to_array($this->composite->provideFor($this->exception));
    }
}
