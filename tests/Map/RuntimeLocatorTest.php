<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandListenerInterface;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Exception\InvalidHandlerException;
use Componenta\CQRS\Command\Locator\CommandHandlerLocator;
use Componenta\CQRS\Command\Locator\CommandListenersLocator;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Map\CommandListenerDescriptor;
use Componenta\CQRS\Map\CqrsMap;
use Componenta\CQRS\Map\CqrsMapProviderInterface;
use Componenta\CQRS\Map\HandlerDescriptor;
use Psr\Container\ContainerInterface;

final readonly class RuntimeLocatorTestCommand
{
}

final class RuntimeLocatorTestHandler
{
    public function handle(RuntimeLocatorTestCommand $command): object
    {
        return $command;
    }
}

final readonly class RuntimeLocatorTestListener implements CommandListenerInterface
{
    public function handleEvent(
        CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event,
    ): void {
    }
}

final readonly class RuntimeLocatorTestMapProvider implements CqrsMapProviderInterface
{
    public function __construct(private CqrsMap $map)
    {
    }

    public function map(): CqrsMap
    {
        return $this->map;
    }
}

final class RuntimeLocatorTestContainer implements ContainerInterface
{
    /** @var array<string, int> */
    public array $gets = [];

    /** @param array<string, mixed> $entries */
    public function __construct(private readonly array $entries)
    {
    }

    public function get(string $id): mixed
    {
        $this->gets[$id] = ($this->gets[$id] ?? 0) + 1;

        return $this->entries[$id] ?? throw new RuntimeException($id);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}

it('resolves a handler service and binds its callable only once', function (): void {
    $map = new CqrsMap(commandHandlers: [
        RuntimeLocatorTestCommand::class => new HandlerDescriptor(
            RuntimeLocatorTestHandler::class,
            'handle',
        ),
    ]);
    $container = new RuntimeLocatorTestContainer([
        RuntimeLocatorTestHandler::class => new RuntimeLocatorTestHandler(),
    ]);
    $locator = new CommandHandlerLocator(
        new RuntimeLocatorTestMapProvider($map),
        $container,
    );
    $command = new RuntimeLocatorTestCommand();

    $first = $locator->locateFor($command);
    $second = $locator->locateFor($command);

    expect($first)->toBe($second)
        ->and($first($command))->toBe($command)
        ->and($container->gets)->toBe([RuntimeLocatorTestHandler::class => 1]);
});

it('reports invalid mapped handler services with the documented locator exception', function (): void {
    foreach ([
        ['__invoke', 'is not invokable'],
        ['missingMethod', 'has no public callable method'],
    ] as [$method, $message]) {
        $map = new CqrsMap(commandHandlers: [
            RuntimeLocatorTestCommand::class => new HandlerDescriptor('invalid.handler', $method),
        ]);
        $locator = new CommandHandlerLocator(
            new RuntimeLocatorTestMapProvider($map),
            new RuntimeLocatorTestContainer(['invalid.handler' => new stdClass()]),
        );

        expect(fn() => $locator->locateFor(new RuntimeLocatorTestCommand()))
            ->toThrow(InvalidHandlerException::class, $message);
    }
});

it('filters listener events before container access and memoizes instances by service id', function (): void {
    $map = new CqrsMap(commandListeners: [
        RuntimeLocatorTestCommand::class => [
            new CommandListenerDescriptor(
                'listener.skipped',
                [CommandFailedEvent::class],
                100,
            ),
            new CommandListenerDescriptor('listener.matching', [], 50),
            new CommandListenerDescriptor(
                'listener.matching',
                [CommandProcessEvent::class],
                0,
            ),
        ],
    ]);
    $listener = new RuntimeLocatorTestListener();
    $container = new RuntimeLocatorTestContainer([
        'listener.skipped' => new RuntimeLocatorTestListener(),
        'listener.matching' => $listener,
    ]);
    $locator = new CommandListenersLocator(
        new RuntimeLocatorTestMapProvider($map),
        $container,
    );
    $event = new CommandProcessEvent(Operation::create(new RuntimeLocatorTestCommand()));

    expect(iterator_to_array($locator->locateFor($event)))->toBe([$listener, $listener])
        ->and($container->gets)->toBe(['listener.matching' => 1]);
});
