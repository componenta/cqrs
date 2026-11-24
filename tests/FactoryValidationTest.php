<?php

declare(strict_types=1);

use Componenta\Config\Config;
use Componenta\Config\ContainerValue;
use Componenta\Config\Environment;
use Componenta\CQRS\Command\Factory\CommandBusFactory;
use Componenta\CQRS\Command\Factory\EventMiddlewareFactory;
use Componenta\CQRS\Command\Factory\OperationFactoryFactory;
use Componenta\CQRS\Command\Factory\HandleCommandHandlerFactory;
use Componenta\CQRS\Command\Locator\CommandHandlerLocatorInterface;
use Componenta\CQRS\Command\Locator\CommandListenersLocatorInterface;
use Componenta\CQRS\Command\Event\CommandProcessEvent;
use Componenta\CQRS\Command\Event\CommandProcessedEvent;
use Componenta\CQRS\Command\Event\CommandFailedEvent;
use Componenta\CQRS\Command\Middleware\HandleCommandHandler;
use Componenta\CQRS\Command\Middleware\OperationHandlerInterface;
use Componenta\CQRS\Command\OperationInterface;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\OperationFactoryInterface;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Exception\InvalidConfigurationException;
use Componenta\Identity\UuidFactory;
use Componenta\Identity\UuidFactoryInterface;
use Componenta\Identity\UuidGenerationVersion;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Componenta\CQRS\Query\Factory\HandleQueryFactory;
use Componenta\CQRS\Query\Factory\QueryBusFactory;
use Componenta\CQRS\Query\HandleQuery;
use Componenta\CQRS\Query\Locator\QueryHandlerLocatorInterface;
use Componenta\CQRS\Tests\Fixture\FakeContainer;
use Componenta\DI\CallableInvoker;
use Componenta\DI\CallableInvokerInterface;

function commandHandlerForFactoryTests(): HandleCommandHandler
{
    $locator = new class () implements CommandHandlerLocatorInterface {
        public function locateFor(object $command): callable
        {
            return static fn (object $message): object => $message;
        }
    };

    return new HandleCommandHandler($locator, new CallableInvoker());
}

function queryHandlerForFactoryTests(): HandleQuery
{
    $locator = new class () implements QueryHandlerLocatorInterface {
        public function locateFor(object $query): callable
        {
            return static fn (object $message): object => $message;
        }
    };

    return new HandleQuery($locator, new CallableInvoker());
}

it('rejects a configured command middleware service with the wrong type', function (): void {
    $container = new FakeContainer([
        Config::class => new Config([
            ConfigKey::COMMAND_MIDDLEWARES => ['invalid.middleware'],
        ], new \Componenta\Config\Environment([])),
        HandleCommandHandler::class => commandHandlerForFactoryTests(),
        'invalid.middleware' => new stdClass(),
    ]);

    expect(fn () => (new CommandBusFactory())($container))
        ->toThrow(LogicException::class, 'must implement');
});

it('accepts a decorated terminal command handler through its public interface', function (): void {
    $terminal = new class () implements OperationHandlerInterface {
        public function handle(OperationInterface $operation): OperationInterface
        {
            return $operation;
        }
    };
    $container = new FakeContainer([
        Config::class => new Config([
            ConfigKey::COMMAND_MIDDLEWARES => [],
        ], new \Componenta\Config\Environment([])),
        HandleCommandHandler::class => $terminal,
    ]);

    $bus = (new CommandBusFactory())($container);
    $command = new stdClass();

    expect($bus->dispatch($command)->command)->toBe($command);
});

it('injects the configured operation factory into the command bus', function (): void {
    $operationFactory = new readonly class () implements OperationFactoryInterface {
        public function create(object $command, array $attributes = []): OperationInterface
        {
            return Operation::create($command, [...$attributes, 'factory' => 'container']);
        }
    };
    $container = new FakeContainer([
        Config::class => new Config([ConfigKey::COMMAND_MIDDLEWARES => []], new \Componenta\Config\Environment([])),
        HandleCommandHandler::class => commandHandlerForFactoryTests(),
        OperationFactoryInterface::class => $operationFactory,
    ]);

    $operation = (new CommandBusFactory())($container)->dispatch(new stdClass());

    expect($operation->attributes)->toBe(['factory' => 'container']);
});

it('rejects an invalid configured operation factory', function (): void {
    $container = new FakeContainer([
        Config::class => new Config([ConfigKey::COMMAND_MIDDLEWARES => []], new \Componenta\Config\Environment([])),
        HandleCommandHandler::class => commandHandlerForFactoryTests(),
        OperationFactoryInterface::class => new stdClass(),
    ]);

    expect(fn () => (new CommandBusFactory())($container))
        ->toThrow(LogicException::class, OperationFactoryInterface::class);
});

it('rejects a configured query middleware service with the wrong type', function (): void {
    $container = new FakeContainer([
        Config::class => new Config([
            ConfigKey::QUERY_MIDDLEWARES => ['invalid.middleware'],
        ], new \Componenta\Config\Environment([])),
        HandleQuery::class => queryHandlerForFactoryTests(),
        'invalid.middleware' => new stdClass(),
    ]);

    expect(fn () => (new QueryBusFactory())($container))
        ->toThrow(LogicException::class, 'must implement');
});

it('rejects an invalid command handler locator dependency', function (): void {
    $container = new FakeContainer([
        CommandHandlerLocatorInterface::class => new stdClass(),
        CallableInvokerInterface::class => new CallableInvoker(),
    ]);

    expect(fn () => (new HandleCommandHandlerFactory())($container))
        ->toThrow(LogicException::class, CommandHandlerLocatorInterface::class);
});

it('rejects an invalid query callable invoker dependency', function (): void {
    $locator = new class () implements QueryHandlerLocatorInterface {
        public function locateFor(object $query): callable
        {
            return static fn (): null => null;
        }
    };
    $container = new FakeContainer([
        QueryHandlerLocatorInterface::class => $locator,
        CallableInvokerInterface::class => new stdClass(),
    ]);

    expect(fn () => (new HandleQueryFactory())($container))
        ->toThrow(LogicException::class, CallableInvokerInterface::class);
});

it('rejects invalid registered optional clock or UUID factory rather than silently falling back', function (string $id, mixed $invalid): void {
    $container = new FakeContainer([$id => $invalid]);

    expect(fn () => (new \Componenta\CQRS\Command\Factory\OperationFactoryFactory())($container))
        ->toThrow(\Componenta\CQRS\Exception\InvalidConfigurationException::class, $id);
})->with([
    [\Psr\Clock\ClockInterface::class, null],
    [\Psr\Clock\ClockInterface::class, new stdClass()],
    [\Componenta\Identity\UuidFactoryInterface::class, null],
    [\Componenta\Identity\UuidFactoryInterface::class, new stdClass()],
]);

it('honors the clock and UUID binding in the command bus factory standalone fallback', function (): void {
    $time = new DateTimeImmutable('2025-01-01T10:00:00.123456+09:00');
    $clock = new class ($time) implements ClockInterface {
        public function __construct(private readonly DateTimeImmutable $time) {}
        public function now(): DateTimeImmutable { return $this->time; }
    };
    $container = new FakeContainer([
        Config::class => new Config([], new Environment([])),
        HandleCommandHandler::class => commandHandlerForFactoryTests(),
        ClockInterface::class => $clock,
        UuidFactoryInterface::class => new UuidFactory(UuidGenerationVersion::V4),
    ]);

    $operation = (new CommandBusFactory())($container)->dispatch(new stdClass());

    expect($operation->createdAt)->toBe($time)
        ->and($operation->id->version)->toBe(4);
});

it('rejects invalid clock bindings in the handler and event middleware factories', function (mixed $invalid): void {
    $locator = new class implements CommandHandlerLocatorInterface {
        public function locateFor(object $command): callable { return static fn (): null => null; }
    };
    $listeners = new class implements CommandListenersLocatorInterface {
        public function locateFor(CommandProcessEvent|CommandProcessedEvent|CommandFailedEvent $event): iterable
        {
            return [];
        }
    };
    $config = new Config([], new Environment([]));
    $container = new FakeContainer([
        CommandHandlerLocatorInterface::class => $locator,
        CommandListenersLocatorInterface::class => $listeners,
        CallableInvokerInterface::class => new CallableInvoker(),
        ClockInterface::class => $invalid,
    ]);

    expect(fn () => (new HandleCommandHandlerFactory())($container))
        ->toThrow(InvalidConfigurationException::class, ClockInterface::class)
        ->and(fn () => (new EventMiddlewareFactory())(new ContainerValue($container, $config)))
        ->toThrow(InvalidConfigurationException::class, ClockInterface::class);
})->with([null, new stdClass()]);

it('does not replace errors from the container with UTC defaults', function (bool $failOnHas): void {
    $failure = new RuntimeException('clock service failure');
    $container = new class ($failure, $failOnHas) implements ContainerInterface {
        public function __construct(private readonly Throwable $failure, private readonly bool $failOnHas) {}
        public function has(string $id): bool
        {
            if ($id === ClockInterface::class && $this->failOnHas) {
                throw $this->failure;
            }
            return $id === ClockInterface::class;
        }
        public function get(string $id): mixed
        {
            throw $this->failure;
        }
    };

    try {
        (new OperationFactoryFactory())($container);
        test()->fail('Expected original container failure.');
    } catch (RuntimeException $caught) {
        expect($caught)->toBe($failure);
    }
})->with([true, false]);
