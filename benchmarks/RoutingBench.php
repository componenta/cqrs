<?php

declare(strict_types=1);

namespace Componenta\CQRS\Benchmarks;

use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandListenerInterface;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Locator\CommandListenersLocator;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\Resolver\CommandNameResolverInterface;
use Componenta\CQRS\Command\Resolver\CompositeCommandNameResolver;
use Componenta\CQRS\Query\Resolver\CompositeQueryNameResolver;
use Componenta\CQRS\Query\Resolver\QueryNameResolverInterface;
use PhpBench\Attributes as Bench;
use Psr\Container\ContainerInterface;
use stdClass;

#[Bench\Iterations(5)]
#[Bench\Revs(5000)]
#[Bench\Warmup(10)]
final class RoutingBench
{
    private CommandNameResolverInterface $commandResolver;
    private QueryNameResolverInterface $queryResolver;
    private object $message;
    private array $listeners;
    private ContainerInterface $container;
    private CommandListenersLocator $locator;
    private CommandProcessEvent $event;

    public function provideResolverCounts(): iterable
    {
        foreach ([0, 1, 4, 16] as $count) {
            yield 'delegates-' . $count => ['count' => $count];
        }
    }

    public function prepareResolvers(array $params): void
    {
        $unsupported = new RoutingBenchResolver(false);
        $fallback = new RoutingBenchResolver(true);
        $delegates = array_fill(0, $params['count'], $unsupported);
        $this->commandResolver = new CompositeCommandNameResolver($fallback, ...$delegates);
        $this->queryResolver = new CompositeQueryNameResolver($fallback, ...$delegates);
        $this->message = new stdClass();
        if (!$this->commandResolver->supports($this->message) || !$this->queryResolver->supports($this->message)) {
            throw new \RuntimeException('Invalid resolver fixture.');
        }
    }

    #[Bench\BeforeMethods('prepareResolvers')]
    #[Bench\ParamProviders('provideResolverCounts')]
    public function benchCommandResolverSupports(array $params): void
    {
        $this->commandResolver->supports($this->message);
    }

    #[Bench\BeforeMethods('prepareResolvers')]
    #[Bench\ParamProviders('provideResolverCounts')]
    public function benchQueryResolverSupports(array $params): void
    {
        $this->queryResolver->supports($this->message);
    }

    public function provideListeners(): iterable
    {
        foreach ([1, 32, 256] as $count) {
            foreach (['filtered', 'mixed', 'all'] as $kind) {
                yield $kind . '-' . $count => ['count' => $count, 'kind' => $kind];
            }
        }
    }

    public function prepareListeners(array $params): void
    {
        $this->listeners = [stdClass::class => []];
        $expected = 0;
        for ($i = 0; $i < $params['count']; ++$i) {
            $events = match ($params['kind']) {
                'filtered' => [CommandFailedEvent::class],
                'mixed' => $i % 4 === 0 ? [CommandProcessEvent::class] : [CommandFailedEvent::class],
                'all' => [],
            };
            if ($events === [] || $events === [CommandProcessEvent::class]) {
                ++$expected;
            }
            $this->listeners[stdClass::class][] = [
                'service' => 'listener.' . $i, 'events' => $events, 'priority' => 0,
            ];
        }
        $this->container = new RoutingBenchContainer(new RoutingBenchListener());
        $this->locator = new CommandListenersLocator($this->listeners, $this->container);
        $this->event = new CommandProcessEvent(Operation::create(new stdClass()));
        if (count(iterator_to_array($this->locator->locateFor($this->event))) !== $expected) {
            throw new \RuntimeException('Invalid listener fixture.');
        }
    }

    #[Bench\BeforeMethods('prepareListeners')]
    #[Bench\ParamProviders('provideListeners')]
    public function benchLocateListeners(array $params): void
    {
        iterator_to_array($this->locator->locateFor($this->event));
    }

    #[Bench\BeforeMethods('prepareListeners')]
    #[Bench\ParamProviders('provideListeners')]
    #[Bench\Revs(50)]
    public function benchBuildListenerLocator(array $params): void
    {
        new CommandListenersLocator($this->listeners, $this->container);
    }
}

final readonly class RoutingBenchResolver implements CommandNameResolverInterface, QueryNameResolverInterface
{
    public function __construct(private bool $supported) {}

    public function supports(object $message): bool
    {
        return $this->supported;
    }

    public function resolve(object $message): string
    {
        return $message::class;
    }
}

final readonly class RoutingBenchContainer implements ContainerInterface
{
    public function __construct(private CommandListenerInterface $listener) {}

    public function get(string $id): mixed
    {
        return $this->listener;
    }

    public function has(string $id): bool
    {
        return true;
    }
}

final readonly class RoutingBenchListener implements CommandListenerInterface
{
    public function handleEvent(CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event): void
    {
    }
}
