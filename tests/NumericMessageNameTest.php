<?php

declare(strict_types=1);

use Componenta\Config\Config;
use Componenta\Config\ContainerValue;
use Componenta\Config\Environment;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandListenerInterface;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Locator\CommandHandlerLocator;
use Componenta\CQRS\Command\Locator\CommandListenersLocator;
use Componenta\CQRS\Command\NamedCommandInterface;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Factory\CqrsMapsFactory;
use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;
use Componenta\CQRS\Query\Locator\QueryHandlerLocator;
use Componenta\CQRS\Query\NamedQueryInterface;
use Componenta\CQRS\Tests\Fixture\FakeContainer;

it('preserves numeric string message names across configured handlers and listeners', function (string $name): void {
    $message = new class ($name) implements NamedCommandInterface, NamedQueryInterface {
        public function __construct(public string $commandName)
        {
        }
        public string $queryName { get => $this->commandName; }
    };
    $listener = new class () implements CommandListenerInterface {
        public array $names = [];
        public function handleEvent(CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event): void
        {
            $this->names[] = $event->operation->command->commandName;
        }
    };
    $container = new FakeContainer([
        'command.handler' => static fn (NamedCommandInterface $command): string => 'command:' . $command->commandName,
        'query.handler' => static fn (NamedQueryInterface $query): string => 'query:' . $query->queryName,
        'listener' => $listener,
    ]);
    $maps = (new CqrsMapsFactory())(new ContainerValue($container, new Config([
        'cqrs.command_handlers' => [['message' => $name, 'service' => 'command.handler', 'method' => '__invoke']],
        'cqrs.query_handlers' => [['message' => $name, 'service' => 'query.handler', 'method' => '__invoke']],
        'cqrs.command_listeners' => [['message' => $name, 'service' => 'listener']],
    ], new Environment([]))));

    $commands = new CommandHandlerLocator($maps['command_handlers'], $container);
    $queries = new QueryHandlerLocator($maps['query_handlers'], $container);
    $event = new CommandProcessEvent(Operation::create($message));
    foreach ((new CommandListenersLocator($maps['command_listeners'], $container))->locateFor($event) as $resolved) {
        $resolved->handleEvent($event);
    }

    expect($commands->supports($message))->toBeTrue()
        ->and(($commands->locateFor($message))($message))->toBe('command:' . $name)
        ->and($queries->supports($message))->toBeTrue()
        ->and(($queries->locateFor($message))($message))->toBe('query:' . $name)
        ->and($listener->names)->toBe([$name]);
})->with(['0', '123', '-1', '001']);

it('still rejects an integer registration value instead of a string message name', function (string $key): void {
    $container = new ContainerValue(new FakeContainer(), new Config([
        $key => [['message' => 123, 'service' => 'handler', 'method' => '__invoke']],
    ], new Environment([])));
    expect(fn () => (new CqrsMapsFactory())($container))->toThrow(InvalidCqrsMapException::class, '.message');
})->with(['cqrs.command_handlers', 'cqrs.query_handlers', 'cqrs.command_listeners']);

it('keeps distinct numeric names in deterministic order regardless of registration order', function (): void {
    $registrations = array_map(static fn (string $name): array => [
        'message' => $name, 'service' => 'handler.' . $name, 'method' => '__invoke',
    ], ['1', '01', '1.0', '10', '2']);
    $create = static fn (array $values): array => (new CqrsMapsFactory())(new ContainerValue(
        new FakeContainer(),
        new Config(['cqrs.command_handlers' => $values], new Environment([])),
    ));

    $forward = $create($registrations);
    $reverse = $create(array_reverse($registrations));

    expect($forward)->toBe($reverse);
    expect(array_keys($forward['command_handlers']))->toBe(['01', 1, '1.0', 10, 2]);
    expect($forward['command_handlers'][1]['service'])->toBe('handler.1')
        ->and($forward['command_handlers']['01']['service'])->toBe('handler.01');
});
