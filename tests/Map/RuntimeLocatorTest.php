<?php

declare(strict_types=1);

use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandListenerInterface;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Exception\InvalidHandlerException;
use Componenta\CQRS\Command\Exception\InvalidListenerException;
use Componenta\CQRS\Command\Locator\CommandHandlerLocator;
use Componenta\CQRS\Command\Locator\CommandListenersLocator;
use Componenta\CQRS\Command\Operation;
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

it('resolves a handler service through the container for every locate', function (): void {
    $map = [
        RuntimeLocatorTestCommand::class => ['service' => RuntimeLocatorTestHandler::class, 'method' => 'handle'],
    ];
    $container = new RuntimeLocatorTestContainer([
        RuntimeLocatorTestHandler::class => new RuntimeLocatorTestHandler(),
    ]);
    $locator = new CommandHandlerLocator(
        $map,
        $container,
    );
    $command = new RuntimeLocatorTestCommand();

    $first = $locator->locateFor($command);
    $second = $locator->locateFor($command);

    expect($first($command))->toBe($command)
        ->and($second($command))->toBe($command)
        ->and($container->gets)->toBe([RuntimeLocatorTestHandler::class => 2]);
});

it('reports invalid mapped handler services with the documented locator exception', function (): void {
    foreach ([
        ['__invoke', 'is not invokable'],
        ['missingMethod', 'has no public callable method'],
    ] as [$method, $message]) {
        $map = [
            RuntimeLocatorTestCommand::class => ['service' => 'invalid.handler', 'method' => $method],
        ];
        $locator = new CommandHandlerLocator(
            $map,
            new RuntimeLocatorTestContainer(['invalid.handler' => new stdClass()]),
        );

        expect(fn () => $locator->locateFor(new RuntimeLocatorTestCommand()))
            ->toThrow(InvalidHandlerException::class, $message);
    }
});

it('reports invalid mapped listener services with a typed locator exception', function (): void {
    $map = [
        RuntimeLocatorTestCommand::class => [
            ['service' => 'invalid.listener', 'events' => [], 'priority' => 0],
        ],
    ];
    $locator = new CommandListenersLocator(
        $map,
        new RuntimeLocatorTestContainer(['invalid.listener' => new stdClass()]),
    );
    $event = new CommandProcessEvent(Operation::create(new RuntimeLocatorTestCommand()));

    expect(fn () => iterator_to_array($locator->locateFor($event)))
        ->toThrow(InvalidListenerException::class, 'must implement');
});

it('filters listener events before container access and resolves matching descriptors through the container', function (): void {
    $map = [
        RuntimeLocatorTestCommand::class => [
            ['service' => 'listener.skipped', 'events' => [CommandFailedEvent::class], 'priority' => 100],
            ['service' => 'listener.matching', 'events' => [], 'priority' => 50],
            ['service' => 'listener.matching', 'events' => [CommandProcessEvent::class], 'priority' => 0],
        ],
    ];
    $listener = new RuntimeLocatorTestListener();
    $container = new RuntimeLocatorTestContainer([
        'listener.skipped' => new RuntimeLocatorTestListener(),
        'listener.matching' => $listener,
    ]);
    $locator = new CommandListenersLocator(
        $map,
        $container,
    );
    $event = new CommandProcessEvent(Operation::create(new RuntimeLocatorTestCommand()));

    expect(iterator_to_array($locator->locateFor($event)))->toBe([$listener, $listener])
        ->and($container->gets)->toBe(['listener.matching' => 2]);
});
