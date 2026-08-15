<?php

declare(strict_types=1);

use Componenta\Config\Config;
use Componenta\CQRS\Command\Factory\CommandBusFactory;
use Componenta\CQRS\Command\Factory\HandleCommandHandlerFactory;
use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\CQRS\Command\Middleware\OperationHandlerInterface;
use Componenta\CQRS\Command\OperationInterface;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationFactoryInterface;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Query\Factory\HandleQueryFactory;
use Componenta\CQRS\Query\Factory\QueryBusFactory;
use Componenta\CQRS\Query\HandleQuery;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\CQRS\Tests\Fixture\FakeContainer;
use Componenta\DI\CallableInvoker;
use Componenta\DI\CallableInvokerInterface;

function commandHandlerForFactoryTests(): HandleCommandHandler
{
    $locator = new class implements CommandHandlerLocatorInterface {
        public function locateFor(object $command): callable
        {
            return static fn(object $message): object => $message;
        }
    };

    return new HandleCommandHandler($locator, new CallableInvoker());
}

function queryHandlerForFactoryTests(): HandleQuery
{
    $locator = new class implements QueryHandlerLocatorInterface {
        public function locateFor(object $query): callable
        {
            return static fn(object $message): object => $message;
        }
    };

    return new HandleQuery($locator, new CallableInvoker());
}

it('rejects a configured command middleware service with the wrong type', function (): void {
    $container = new FakeContainer([
        ConfigKey::CONFIG => new Config([
            ConfigKey::COMMAND_MIDDLEWARES => ['invalid.middleware'],
        ]),
        HandleCommandHandler::class => commandHandlerForFactoryTests(),
        'invalid.middleware' => new stdClass(),
    ]);

    expect(fn() => (new CommandBusFactory())($container))
        ->toThrow(LogicException::class, 'must implement');
});

it('accepts a decorated terminal command handler through its public interface', function (): void {
    $terminal = new class implements OperationHandlerInterface {
        public function handle(OperationInterface $operation): OperationInterface
        {
            return $operation;
        }
    };
    $container = new FakeContainer([
        ConfigKey::CONFIG => new Config([
            ConfigKey::COMMAND_MIDDLEWARES => [],
        ]),
        HandleCommandHandler::class => $terminal,
    ]);

    $bus = (new CommandBusFactory())($container);
    $command = new stdClass();

    expect($bus->dispatch($command)->command)->toBe($command);
});

it('injects the configured operation factory into the command bus', function (): void {
    $operationFactory = new readonly class implements OperationFactoryInterface {
        public function create(object $command, array $attributes = []): OperationInterface
        {
            return Operation::create($command, [...$attributes, 'factory' => 'container']);
        }
    };
    $container = new FakeContainer([
        ConfigKey::CONFIG => new Config([ConfigKey::COMMAND_MIDDLEWARES => []]),
        HandleCommandHandler::class => commandHandlerForFactoryTests(),
        OperationFactoryInterface::class => $operationFactory,
    ]);

    $operation = (new CommandBusFactory())($container)->dispatch(new stdClass());

    expect($operation->attributes)->toBe(['factory' => 'container']);
});

it('rejects an invalid configured operation factory', function (): void {
    $container = new FakeContainer([
        ConfigKey::CONFIG => new Config([ConfigKey::COMMAND_MIDDLEWARES => []]),
        HandleCommandHandler::class => commandHandlerForFactoryTests(),
        OperationFactoryInterface::class => new stdClass(),
    ]);

    expect(fn() => (new CommandBusFactory())($container))
        ->toThrow(LogicException::class, OperationFactoryInterface::class);
});

it('rejects a configured query middleware service with the wrong type', function (): void {
    $container = new FakeContainer([
        ConfigKey::CONFIG => new Config([
            ConfigKey::QUERY_MIDDLEWARES => ['invalid.middleware'],
        ]),
        HandleQuery::class => queryHandlerForFactoryTests(),
        'invalid.middleware' => new stdClass(),
    ]);

    expect(fn() => (new QueryBusFactory())($container))
        ->toThrow(LogicException::class, 'must implement');
});

it('rejects an invalid command handler locator dependency', function (): void {
    $container = new FakeContainer([
        CommandHandlerLocatorInterface::class => new stdClass(),
        CallableInvokerInterface::class => new CallableInvoker(),
    ]);

    expect(fn() => (new HandleCommandHandlerFactory())($container))
        ->toThrow(LogicException::class, CommandHandlerLocatorInterface::class);
});

it('rejects an invalid query callable invoker dependency', function (): void {
    $locator = new class implements QueryHandlerLocatorInterface {
        public function locateFor(object $query): callable
        {
            return static fn(): null => null;
        }
    };
    $container = new FakeContainer([
        QueryHandlerLocatorInterface::class => $locator,
        CallableInvokerInterface::class => new stdClass(),
    ]);

    expect(fn() => (new HandleQueryFactory())($container))
        ->toThrow(LogicException::class, CallableInvokerInterface::class);
});
