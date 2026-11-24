<?php

declare(strict_types=1);

namespace Componenta\CQRS\Benchmarks;

use Componenta\Config\ConfigFactory;
use Componenta\Config\ConfigProvider as BaseProvider;
use Componenta\Config\Environment;
use Componenta\CQRS\Command\BatchCommandBus;
use Componenta\CQRS\Command\CommandBusInterface;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandListenerInterface;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\ConfigProvider;
use Componenta\CQRS\Query\Context\Context;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\CQRS\Query\QueryBusInterface;
use Componenta\DI\ContainerFactory;
use PhpBench\Attributes as Bench;

#[Bench\Iterations(7)]
#[Bench\Revs(2000)]
#[Bench\Warmup(100)]
final class RuntimeBench
{
    private CommandBusInterface $commands;
    private CommandBusInterface $commandsWithEvents;
    private QueryBusInterface $queries;
    private BatchCommandBus $batch;
    private RuntimeBenchMessage $message;
    private Context $context;
    private CommandHandlerLocatorInterface $commandLocator;
    private QueryHandlerLocatorInterface $queryLocator;
    private CommandListenersLocatorInterface $listenerLocator;
    private CommandProcessedEvent $event;
    private CommandProcessEvent $filteredEvent;
    private array $batchMessages;

    public function prepare(): void
    {
        $composition = (new ConfigFactory())->create(new Environment([]), new ConfigProvider(), new RuntimeBenchProvider(false));
        $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
        $eventComposition = (new ConfigFactory())->create(new Environment([]), new ConfigProvider(), new RuntimeBenchProvider(true));
        $eventContainer = (new ContainerFactory())->create($eventComposition->config, $eventComposition->dependencies)->container;

        $this->commands = $container->get(CommandBusInterface::class);
        $this->commandsWithEvents = $eventContainer->get(CommandBusInterface::class);
        $this->queries = $container->get(QueryBusInterface::class);
        $this->batch = new BatchCommandBus($this->commands);
        $this->message = new RuntimeBenchMessage(41);
        $this->context = new Context(['tenant' => 'bench', 'trace' => 'bench']);
        $this->commandLocator = $container->get(CommandHandlerLocatorInterface::class);
        $this->queryLocator = $container->get(QueryHandlerLocatorInterface::class);
        $this->listenerLocator = $container->get(CommandListenersLocatorInterface::class);
        $operation = $this->commands->dispatch($this->message);
        $this->event = new CommandProcessedEvent($operation);
        $this->filteredEvent = new CommandProcessEvent(Operation::create($this->message));
        $this->batchMessages = array_fill(0, 16, $this->message);

        if ($operation->result?->value !== 42
            || $this->commandsWithEvents->dispatch($this->message)->result?->value !== 42
            || $this->queries->handle($this->message, $this->context) !== 42
            || count(iterator_to_array($this->listenerLocator->locateFor($this->event))) !== 1
            || iterator_to_array($this->listenerLocator->locateFor($this->filteredEvent)) !== []) {
            throw new \RuntimeException('Invalid real DI runtime benchmark fixture.');
        }
    }

    #[Bench\BeforeMethods('prepare')]
    public function benchCommandDispatch(): void
    {
        $this->commands->dispatch($this->message, ['trace' => 'bench']);
    }

    #[Bench\BeforeMethods('prepare')]
    public function benchCommandDispatchWithEvents(): void
    {
        $this->commandsWithEvents->dispatch($this->message, ['trace' => 'bench']);
    }

    #[Bench\BeforeMethods('prepare')]
    public function benchQueryDispatch(): void
    {
        $this->queries->handle($this->message, $this->context);
    }

    #[Bench\BeforeMethods('prepare')]
    #[Bench\Revs(250)]
    public function benchBatchDispatch16(): void
    {
        $this->batch->dispatchMany($this->batchMessages);
    }

    #[Bench\BeforeMethods('prepare')]
    public function benchCommandHandlerLookup(): void
    {
        $this->commandLocator->locateFor($this->message);
    }

    #[Bench\BeforeMethods('prepare')]
    public function benchQueryHandlerLookup(): void
    {
        $this->queryLocator->locateFor($this->message);
    }

    #[Bench\BeforeMethods('prepare')]
    public function benchMatchingListenerLookup(): void
    {
        iterator_to_array($this->listenerLocator->locateFor($this->event));
    }

    #[Bench\BeforeMethods('prepare')]
    public function benchFilteredListenerLookup(): void
    {
        iterator_to_array($this->listenerLocator->locateFor($this->filteredEvent));
    }

    #[Bench\Revs(100)]
    public function benchContainerAndBusComposition(): void
    {
        $composition = (new ConfigFactory())->create(new Environment([]), new ConfigProvider(), new RuntimeBenchProvider(true));
        $container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
        $container->get(CommandBusInterface::class);
        $container->get(QueryBusInterface::class);
    }
}

final readonly class RuntimeBenchMessage
{
    public function __construct(public int $value) {}
}

final readonly class RuntimeBenchDependency
{
    public function __construct(public int $offset = 1) {}
}

final readonly class RuntimeBenchHandler
{
    public function __invoke(RuntimeBenchMessage $message, RuntimeBenchDependency $dependency): int
    {
        return $message->value + $dependency->offset;
    }
}

final readonly class RuntimeBenchListener implements CommandListenerInterface
{
    public function handleEvent(CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event): void
    {
    }
}

final class RuntimeBenchProvider extends BaseProvider
{
    public function __construct(private readonly bool $events) {}

    protected function getConfig(): array
    {
        return [
            ConfigKey::COMMAND_HANDLERS => [
                ['message' => RuntimeBenchMessage::class, 'service' => RuntimeBenchHandler::class, 'method' => '__invoke'],
            ],
            ConfigKey::QUERY_HANDLERS => [
                ['message' => RuntimeBenchMessage::class, 'service' => RuntimeBenchHandler::class, 'method' => '__invoke'],
            ],
            ConfigKey::COMMAND_LISTENERS => [
                ['message' => RuntimeBenchMessage::class, 'service' => RuntimeBenchListener::class, 'events' => [CommandProcessedEvent::class]],
            ],
            ConfigKey::COMMAND_MIDDLEWARES => $this->events ? [EventMiddleware::class] : [],
        ];
    }

    protected function getServices(): array
    {
        return [
            RuntimeBenchHandler::class => new RuntimeBenchHandler(),
            RuntimeBenchDependency::class => new RuntimeBenchDependency(),
            RuntimeBenchListener::class => new RuntimeBenchListener(),
        ];
    }
}
