<?php

declare(strict_types=1);

use Componenta\Config\Config;
use Componenta\Config\ContainerValue;
use Componenta\Config\Environment;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Locator\CommandHandlerLocator;
use Componenta\CQRS\Factory\CqrsMapsFactory;
use Componenta\CQRS\Map\Exception\InvalidCqrsMapException;
use Componenta\CQRS\Query\Locator\QueryHandlerLocator;
use Componenta\CQRS\Tests\Fixture\FakeContainer;

it('rejects malformed explicit registrations with their configuration location', function (array $config, string $location): void {
    $container = new ContainerValue(new FakeContainer([]), new Config($config, new Environment([])));
    expect(fn () => (new CqrsMapsFactory())($container))->toThrow(InvalidCqrsMapException::class, $location);
})->with([
    'not a list' => [['cqrs.command_handlers' => ['message' => []]], 'cqrs.command_handlers'],
    'null list' => [['cqrs.query_handlers' => null], 'cqrs.query_handlers'],
    'non array entry' => [['cqrs.command_handlers' => ['handler']], '[0]'],
    'empty message' => [['cqrs.command_handlers' => [['message' => ' ']]], '.message'],
    'empty service' => [['cqrs.command_handlers' => [['message' => 'cmd', 'service' => '', 'method' => '__invoke']]], 'cmd'],
    'invalid method' => [['cqrs.query_handlers' => [['message' => 'query', 'service' => 'h', 'method' => 'bad::method']]], 'query'],
    'unknown field' => [['cqrs.command_handlers' => [['message' => 'cmd', 'service' => 'h', 'method' => 'h', 'unknown' => true]]], 'cmd'],
    'unknown listener field' => [['cqrs.command_listeners' => [['message' => 'cmd', 'service' => 'l', 'extra' => true]]], 'cmd'],
    'listener priority' => [['cqrs.command_listeners' => [['message' => 'cmd', 'service' => 'l', 'priority' => '1']]], 'cmd'],
    'listener events' => [['cqrs.command_listeners' => [['message' => 'cmd', 'service' => 'l', 'events' => [stdClass::class]]]], 'cmd'],
    'duplicate listener' => [['cqrs.command_listeners' => [
        ['message' => 'cmd', 'service' => 'l'],
        ['message' => 'cmd', 'service' => 'l', 'events' => [], 'priority' => 0],
    ]], 'cmd'],
]);

it('normalizes equivalent handlers and deterministically orders listeners', function (): void {
    $container = new ContainerValue(new FakeContainer([]), new Config([
        'cqrs.command_handlers' => [
            ['message' => 'cmd', 'method' => '__invoke', 'service' => 'h'],
            ['message' => 'cmd', 'service' => 'h', 'method' => '__invoke'],
        ],
        'cqrs.command_listeners' => [
            ['message' => 'cmd', 'service' => 'z'],
            ['message' => 'cmd', 'service' => 'a', 'priority' => 10, 'events' => [CommandProcessEvent::class, CommandFailedEvent::class, CommandProcessEvent::class]],
            ['message' => 'cmd', 'service' => 'a'],
        ],
    ], new Environment([])));
    $maps = (new CqrsMapsFactory())($container);
    expect($maps)->toBe([
        'command_handlers' => ['cmd' => ['service' => 'h', 'method' => '__invoke']],
        'query_handlers' => [],
        'command_listeners' => ['cmd' => [
            ['service' => 'a', 'events' => [CommandFailedEvent::class, CommandProcessEvent::class], 'priority' => 10],
            ['service' => 'a', 'events' => [], 'priority' => 0],
            ['service' => 'z', 'events' => [], 'priority' => 0],
        ]],
    ]);
});

it('validates handler arrays at the public locator constructor', function (string $locator): void {
    expect(fn () => new $locator(['message' => ['service' => ' ', 'method' => '__invoke']], new FakeContainer([])))
        ->toThrow(InvalidCqrsMapException::class, 'message');
})->with([CommandHandlerLocator::class, QueryHandlerLocator::class]);

it('rejects listener groups that are not lists', function (): void {
    expect(fn () => new \Componenta\CQRS\Command\Locator\CommandListenersLocator([
        'command' => ['named' => ['service' => 'listener']],
    ], new FakeContainer([])))->toThrow(InvalidCqrsMapException::class, 'command');
});

it('produces deterministic message ordering independently of provider registration order', function (): void {
    $maps = (new CqrsMapsFactory())(new ContainerValue(new FakeContainer([]), new Config([
        'cqrs.command_handlers' => [
            ['message' => 'z', 'service' => 'z.handler', 'method' => 'run'],
            ['message' => 'a', 'service' => 'a.handler', 'method' => 'run'],
        ],
        'cqrs.command_listeners' => [
            ['message' => 'z', 'service' => 'z.listener'],
            ['message' => 'a', 'service' => 'a.listener'],
        ],
    ], new Environment([]))));

    expect(array_keys($maps['command_handlers']))->toBe(['a', 'z']);
    expect(array_keys($maps['command_listeners']))->toBe(['a', 'z']);
});


it('rejects duplicate listeners after canonicalizing event order and repetitions', function (): void {
    $container = new ContainerValue(new FakeContainer([]), new Config([
        'cqrs.command_listeners' => [
            ['message' => 'cmd', 'service' => 'listener', 'priority' => 7,
                'events' => [CommandProcessEvent::class, CommandFailedEvent::class]],
            ['message' => 'cmd', 'service' => 'listener', 'priority' => 7,
                'events' => [CommandFailedEvent::class, CommandProcessEvent::class, CommandFailedEvent::class]],
        ],
    ], new Environment([])));

    expect(fn () => (new CqrsMapsFactory())($container))
        ->toThrow(InvalidCqrsMapException::class, 'repeats an identical listener registration');
});

it('keeps distinct descriptors for one service in canonical priority and event order', function (): void {
    $container = new ContainerValue(new FakeContainer([]), new Config([
        'cqrs.command_listeners' => [
            ['message' => 'cmd', 'service' => 'listener', 'priority' => 1, 'events' => [CommandProcessEvent::class]],
            ['message' => 'cmd', 'service' => 'listener', 'priority' => 2, 'events' => [CommandProcessEvent::class]],
            ['message' => 'cmd', 'service' => 'listener', 'priority' => 2, 'events' => [CommandFailedEvent::class]],
        ],
    ], new Environment([])));

    expect((new CqrsMapsFactory())($container)['command_listeners']['cmd'])->toBe([
        ['service' => 'listener', 'events' => [CommandFailedEvent::class], 'priority' => 2],
        ['service' => 'listener', 'events' => [CommandProcessEvent::class], 'priority' => 2],
        ['service' => 'listener', 'events' => [CommandProcessEvent::class], 'priority' => 1],
    ]);
});
