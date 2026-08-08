# Componenta CQRS

`componenta/cqrs` is the core CQRS runtime for PHP 8.4+. It provides command and query buses, handler locators, command operation objects, command events, and command/query discovery attributes used by application-level tooling.

The package intentionally contains only the neutral runtime. Middleware that requires policy, locks, queues, database transactions, or console integration lives in separate packages.

## Installation

```bash
composer require componenta/cqrs
```

## Dependencies

| Dependency | Purpose |
|---|---|
| PHP `>=8.4` | Modern language features and strict types. |
| `componenta/arrayable` | Shared array conversion contract. |
| `componenta/config` | Config provider integration. |
| `componenta/di` | Handler/listener invocation through `CallableInvokerInterface`. |
| `componenta/iterator` | Iterator helpers for locators. |
| `componenta/reflection` | Metadata helpers for command attributes. |
| `psr/container` | Service lookup. |
| `ramsey/uuid` | Operation identifiers. |

## Optional Packages

| Package | Adds |
|---|---|
| `componenta/cqrs-policy` | Command and query policy middleware. Use `#[Allow]` from `componenta/policy` for public flows; do not use skip-policy flags as a public-access model. |
| `componenta/cqrs-retry` | Command retry middleware for commands marked with `#[Retry]`. |
| `componenta/cqrs-lock` | Symfony Lock command middleware for commands marked with `#[Lock]`. |
| `componenta/cqrs-transaction-cycle` | Cycle Database transaction middleware. |
| `componenta/cqrs-transport` | Transport contracts, serializer, async middleware, and command worker. |
| `componenta/cqrs-transport-cycle` | Cycle Database transport implementation. |
| `componenta/cqrs-transport-console` | Symfony Console worker command. |
| `componenta/cqrs-app` | Attribute discovery and build-time map compilers. |

## Quick Start

```php
use Componenta\CQRS\Command\CommandBusInterface;
use Componenta\Config\ConfigLoader;
use Componenta\DI\ContainerBuilder;
use Componenta\CQRS\ConfigKey;
use Componenta\CQRS\Map\CqrsMap;
use Componenta\CQRS\Map\HandlerDescriptor;

final readonly class CalculateTotalCommand
{
    public function __construct(
        public int $price,
        public int $quantity,
    ) {}
}

final readonly class CalculateTotalHandler
{
    public function __invoke(CalculateTotalCommand $command): int
    {
        return $command->price * $command->quantity;
    }
}

$config = ConfigLoader::load(
    null,
    new Componenta\CQRS\ConfigProvider(),
    static fn(): array => [
        ConfigKey::CQRS_MAP => (new CqrsMap(
            commandHandlers: [
                CalculateTotalCommand::class
                    => new HandlerDescriptor(CalculateTotalHandler::class, '__invoke'),
            ],
        ))->toArray(),
    ],
);
$container = ContainerBuilder::configure($config)->build();

/** @var CommandBusInterface $commands */
$commands = $container->get(CommandBusInterface::class);
$operation = $commands->dispatch(new CalculateTotalCommand(price: 50, quantity: 3));

$result = $operation->result?->value; // 150
```

## Configuration

Register the runtime provider:

```php
return [
    new Componenta\CQRS\ConfigProvider(),
];
```

The provider registers:

| Service | Purpose |
|---|---|
| `CommandBusInterface` | Dispatches commands through the configured command middleware chain. |
| `QueryBusInterface` | Handles queries through the configured query middleware chain. |
| `CommandHandlerLocatorInterface` | Resolves command handler descriptors from the active `CqrsMapProviderInterface`. |
| `CommandListenersLocatorInterface` | Filters and resolves command listener descriptors from the same map snapshot. |
| `QueryHandlerLocatorInterface` | Resolves query handler descriptors from the same map snapshot. |
| `CommandMetadataProviderInterface` | Returns any registered command attribute type from the v2 map, with reflection fallback only for unknown commands. |

Important keys:

| Key | Value |
|---|---|
| `ConfigKey::COMMAND_MIDDLEWARES` | Command middleware list in execution order. |
| `ConfigKey::QUERY_MIDDLEWARES` | Query middleware list in execution order. |
| `ConfigKey::CQRS_MAP` | One versioned artifact containing command handlers, listeners, metadata, known commands, and query handlers. |
| `ConfigKey::COMMAND_METADATA_ATTRIBUTES` | Attribute classes that `componenta/cqrs-app` must discover and compile. Optional packages append their own classes. |

Missing middleware keys mean an empty chain. Add optional middleware from the packages listed above when the application needs authorization, retries, locks, transactions, async execution, or workers.

## CQRS Map v2

All runtime locators consume one immutable `CqrsMap` snapshot. Its serialized shape is:

```php
[
    'version' => 2,
    'commands' => [
        'handlers' => [
            Command::class => ['service' => Handler::class, 'method' => '__invoke'],
        ],
        'listeners' => [
            Command::class => [[
                'service' => Listener::class,
                'events' => [CommandProcessedEvent::class],
                'priority' => 100,
            ]],
        ],
        'known' => [Command::class => true],
        'metadata' => [
            Command::class => [
                Attribute::class => ['arguments' => ['value']],
            ],
        ],
    ],
    'queries' => [
        'handlers' => [
            Query::class => ['service' => QueryHandler::class, 'method' => 'handle'],
        ],
    ],
]
```

Empty sections are omitted, so an empty valid artifact is exactly `['version' => 2]`. Serialization is deterministic. Conflicting handlers or metadata fail instead of silently using the last entry; identical listeners are deduplicated and the global listener order is priority descending, then service and event names.

`APP_ENV=production` requires a current v2 artifact. Other environments may start with an empty base map, and `componenta/cqrs-app` can overlay live discovery. Old map keys and unsupported artifact versions throw an exception that asks the operator to clear caches and run `app:build`.

## Commands

A command is an object with data for a state-changing use case. `CommandBusInterface::dispatch(object $command, array $attributes = [])` returns an immutable `OperationInterface` with operation id, attributes, and a result when the command runs synchronously.

Handlers are resolved through `CommandHandlerLocatorInterface`. A map descriptor stores a service id and method; the service callable is resolved lazily through the container and memoized by the locator.

`#[AsCommandHandler(?string $command = null)]` is discovery metadata for `componenta/cqrs-app`. If the command is omitted, `cqrs-app` infers it from the handler parameter.

## Command Middleware

Command middleware implements:

```php
public function execute(OperationInterface $operation, OperationHandlerInterface $handler): OperationInterface;
```

Core middleware:

| Middleware | Responsibility |
|---|---|
| `SequentialMiddleware` | Runs nested dispatches after the root command. |
| `EventMiddleware` | Emits command lifecycle events. |
| `HandleCommandHandler` | Terminal handler that locates and invokes the command handler. The bus factory wires it separately. |

Order is behavior. Keep cross-cutting concerns in middleware instead of hiding them in handlers.

## Command Attributes

| Attribute | Target | Constructor | Behavior |
|---|---|---|---|
| `#[AsCommandHandler]` | class or method | `?string $command = null` | Discovery metadata for command handlers. |
| `#[AsCommandListener]` | class | `string $command, int $priority = 0, array $eventTypes = []` | Discovery metadata for command event listeners. |
| `#[Componenta\CQRS\Transport\Attribute\Async]` | command class | `string $transport = 'default', int $delay = 0` | Metadata supplied by `componenta/cqrs-transport`. |
| `#[Componenta\CQRS\Retry\Attribute\Retry]` | command class | `int $attempts = 3, int $delayMs = 100, float $multiplier = 1.0, int $maxDelayMs = 10000` | Metadata supplied by `componenta/cqrs-retry`. |
| `#[Componenta\CQRS\Lock\Attribute\Lock]` | command class | `string $key, float $ttl = 300.0, bool $blocking = true` | Metadata supplied by `componenta/cqrs-lock`. |


## Command Events

| Event | When |
|---|---|
| `CommandProcessEvent` | Before command execution. |
| `CommandProcessedEvent` | After successful execution. |
| `CommandFailedEvent` | After failure, before the exception is rethrown. |

Listeners are resolved through `CommandListenersLocatorInterface`. `EventMiddleware` suppresses listener failures by default. Use `suppressExceptions: false` for fail-fast development behavior.

## Queries

Queries describe read use cases and return the handler result directly:

```php
use Componenta\CQRS\Query\QueryBusInterface;

$post = $queries->handle(new GetPostQuery($id));
```

`QueryBusInterface::handle(object $query, ContextInterface|array $context = [])` converts arrays to immutable `Context` before middleware receives them.

Handlers are resolved through `QueryHandlerLocatorInterface`. `#[AsQueryHandler(?string $query = null)]` is discovery metadata used by `componenta/cqrs-app`.

## Extension Points

| Replace | Contract | When to use |
|---|---|---|
| Command name | `NamedCommandInterface` or `CommandNameResolverInterface` | The handler-map key must differ from the command class FQCN. |
| Query name | `NamedQueryInterface` or `QueryNameResolverInterface` | The handler-map key must differ from the query class FQCN. |
| Command chain step | `Componenta\CQRS\Command\Middleware\MiddlewareInterface` | A custom step must run around command execution. |
| Query chain step | `Componenta\CQRS\Query\Middleware\MiddlewareInterface` | A custom step must run around query execution. |
| Command event listeners | `CommandListenerInterface` | A side effect should react to command events without changing the command handler. |
| Command metadata | `CommandMetadataProviderInterface` | Metadata must come from a source other than the versioned map and its reflection fallback. `get($command, Attribute::class)` is generic; optional packages register attribute classes through config. |

## Failures

| Failure | Exception |
|---|---|
| Missing command handler | `Componenta\CQRS\Command\Exception\HandlerNotFoundException` |
| Invalid command handler | `Componenta\CQRS\Command\Exception\InvalidHandlerException` |
| Missing query handler | `Componenta\CQRS\Query\Exception\HandlerNotFoundException` |


## Migration From v1

| v1 | v2 |
|---|---|
| `COMMAND_HANDLER_MAP` | `CQRS_MAP['commands']['handlers']` |
| `QUERY_HANDLER_MAP` | `CQRS_MAP['queries']['handlers']` |
| `COMMAND_LISTENER_MAP` | `CQRS_MAP['commands']['listeners']` |
| `COMMAND_ATTRIBUTE_MAP` | `CQRS_MAP['commands']['metadata']` |
| `COMPILED_MAPS` | Removed; map version and environment define the runtime path. |
| `CommandAttributeProviderInterface` | `CommandMetadataProviderInterface` |
| `async()/retry()/lock()` | `get($command, Attribute::class)` |

After upgrading, remove config, discovery, old CQRS, generated resolver, and release-fingerprint caches, then run `APP_ENV=development php bin/console.php app:build`.
