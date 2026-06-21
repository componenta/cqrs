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
use Componenta\CQRS\ConfigKey;

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

return [
    new Componenta\CQRS\ConfigProvider(),

    ConfigKey::COMMAND_HANDLER_MAP => [
        CalculateTotalCommand::class => [CalculateTotalHandler::class, '__invoke'],
    ],
];

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
| `CommandHandlerLocatorInterface` | Reads `ConfigKey::COMMAND_HANDLER_MAP`. |
| `CommandListenersLocatorInterface` | Reads `ConfigKey::COMMAND_LISTENER_MAP`. |
| `QueryHandlerLocatorInterface` | Reads `ConfigKey::QUERY_HANDLER_MAP`. |
| `CommandAttributeProviderInterface` | Reads `#[Async]`, `#[Retry]`, and `#[Lock]` from compiled maps or reflection. |

Important keys:

| Key | Value |
|---|---|
| `ConfigKey::COMMAND_MIDDLEWARES` | Command middleware list in execution order. |
| `ConfigKey::QUERY_MIDDLEWARES` | Query middleware list in execution order. |
| `ConfigKey::COMMAND_HANDLER_MAP` | `Command::class => callable|[Handler::class, method]` map. |
| `ConfigKey::COMMAND_LISTENER_MAP` | Command event listener map. |
| `ConfigKey::QUERY_HANDLER_MAP` | `Query::class => callable|[Handler::class, method]` map. |
| `ConfigKey::COMMAND_ATTRIBUTE_MAP` | Compiled metadata for `#[Async]`, `#[Retry]`, and `#[Lock]`. |
| `ConfigKey::COMPILED_MAPS` | Enables compiled maps. |

`Componenta\CQRS\ConfigProvider` registers empty command and query middleware lists by default. Add optional middleware from the packages listed above when the application needs authorization, retries, locks, transactions, async execution, or workers.

## Commands

A command is an object with data for a state-changing use case. `CommandBusInterface::dispatch(object $command, array $attributes = [])` returns an immutable `OperationInterface` with operation id, attributes, and a result when the command runs synchronously.

Handlers are resolved through `CommandHandlerLocatorInterface`. A handler map entry can be a callable or a `[class-string, method-string]` pair. Class/method pairs are resolved lazily through the container.

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
| `#[Async]` | command class | `string $transport = 'default', int $delay = 0` | Metadata consumed by `componenta/cqrs-transport`. |
| `#[Retry]` | command class | `int $attempts = 3, int $delayMs = 100, float $multiplier = 1.0, int $maxDelayMs = 10000` | Metadata consumed by `componenta/cqrs-retry`. |
| `#[Lock]` | command class | `string $key, float $ttl = 300.0, bool $blocking = true` | Metadata consumed by `componenta/cqrs-lock`. |


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
| Command metadata | `CommandAttributeProviderInterface` | `#[Async]`, `#[Retry]`, and `#[Lock]` must come from a source other than reflection or the standard compiled map. |

## Failures

| Failure | Exception |
|---|---|
| Missing command handler | `Componenta\CQRS\Command\Exception\HandlerNotFoundException` |
| Invalid command handler | `Componenta\CQRS\Command\Exception\InvalidHandlerException` |
| Missing query handler | `Componenta\CQRS\Query\Exception\HandlerNotFoundException` |

