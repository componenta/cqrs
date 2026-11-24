# Componenta CQRS

`componenta/cqrs` is the neutral CQRS runtime for PHP 8.5.13+ with Componenta DI 6.

```bash
composer require componenta/cqrs
```

Register `Componenta\CQRS\ConfigProvider`. It provides the standard command/query buses, locators, operation factory, metadata provider, and the shared arrays of CQRS registrations.

## Commands and operations

```php
$operation = $commands->dispatch(new CreateUserCommand($email));
$result = $operation->result?->value;
```

One `dispatch()` creates one `OperationInterface` and sends it through the complete command pipeline.

An operation contains:

- UUID v7 `id`;
- the `command` instance;
- `createdAt`, the UTC timestamp when this local operation object was created for dispatch;
- `attributes` with a strict `array<string,mixed>` contract;
- optional `OperationResult`, whose `processedAt` records synchronous completion.

A non-null `result` marks completion for every `OperationInterface` implementation, even if `result->value` is null. `withResult()` is single-assignment. The terminal command handler rejects a completed operation before looking up or invoking its handler. This guard does not provide distributed idempotency.

`createdAt` is deliberately not named `startedAt`: operation creation happens before middleware and is not the same thing as handler execution start. Async transports must not restore producer `createdAt` as a worker execution timestamp.

CQRS keeps the operation factory separate from middleware composition:

```php
$bus = new Componenta\CQRS\Command\CommandBus(
    commandHandler: $terminalHandler,
    middlewares: [
        $firstMiddleware,
        $secondMiddleware,
    ],
    operationFactory: $operationFactory,
);
```

The operation factory is optional; `OperationFactory` is used by default.

### Multiple commands

`BatchCommandBus` is the explicit sequential multi-dispatch decorator:

```php
$batch = new Componenta\CQRS\Command\BatchCommandBus($commands);

$operations = $batch->dispatchMany([
    new FirstCommand(),
    new SecondCommand(),
]);
```

Each command is dispatched independently through the wrapped bus and receives its own operation. Explicit `null` (or any non-array value) for a selected class's attributes is rejected before any command runs; a missing class entry defaults to `[]`. Batch execution is sequential, not transactional. Core no longer contains `SequentialMiddleware`; nested `dispatch()` calls use normal reentrant dispatch semantics. Work that must happen after the current command or transaction should use an explicit event, outbox, async transport, or workflow/process manager.

## Command middleware

Command middleware implements:

```php
public function execute(
    OperationInterface $operation,
    OperationHandlerInterface $handler,
): OperationInterface;
```

`HandleCommandHandler` is the terminal handler. `ConfigProvider` registers `EventMiddleware` as a service, but does not insert it into the pipeline automatically. Add it to `ConfigKey::COMMAND_MIDDLEWARES` when command lifecycle listeners should run.

### Middleware order

Middleware execute exactly in the order supplied by application configuration. `CommandBus` validates the middleware collection and compiles that order; it does not infer, reorder, or reject application topology based on other packages.

The order is therefore part of application behavior. For example, with retry and transaction middleware:

```text
RetryMiddleware
  TransactionMiddleware
    handler
```

creates a new transaction for each retry attempt, while:

```text
TransactionMiddleware
  RetryMiddleware
    handler
```

keeps all retry attempts inside one surrounding transaction. Neither topology is rejected by CQRS core; applications choose the semantics they need.

Optional package documentation describes useful ordering patterns and their consequences, but ordering remains configuration responsibility.

## Command lifecycle events

`EventMiddleware` can emit:

- `CommandProcessEvent` before downstream command execution;
- `CommandProcessedEvent` after success;
- `CommandFailedEvent` after failure before rethrow.

Listener failures propagate by default. The position of `EventMiddleware` relative to policy, transport, retry, lock, transaction, or custom middleware is application-defined.

**Retry and notification failures.** With `Retry → Event → handler`, a retry policy that retries an error from `CommandProcessedEvent` may execute an already successful handler again. With `Event → Retry → handler`, events describe the entire dispatch, rather than each inner attempt; failures recovered by the inner retry do not create an outer `CommandFailedEvent`. Neither arrangement provides idempotency by itself. Applications must choose retry/transaction ordering and use idempotency keys or a transactional outbox where required. See [operation-lifecycle design notes](docs/plans/cqrs-operation-lifecycle-contracts.md).

## Queries

```php
$result = $queries->handle(new GetUserQuery($id));
```

`QueryBusInterface::handle(object $query, ContextInterface|array $context = [])` normalizes array context to immutable `Context`. Query context attributes use the same non-empty string-key invariant as operation attributes. The built-in `Context` and `Operation` detach top-level PHP references from caller-owned attribute arrays; nested objects and resources are not deep-cloned.

## Registration and maps

The core package uses arrays and does not scan application classes or read cache files. It requires Config 3 and DI 6. Packages and applications append registration lists through their ConfigProvider:

```php
use Componenta\CQRS\ConfigKey;

final class ApplicationConfigProvider extends \Componenta\Config\ConfigProvider
{
    protected function getConfig(): array
    {
        return [
            ConfigKey::COMMAND_HANDLERS => [
                ['message' => CreateUserCommand::class, 'service' => CreateUserHandler::class, 'method' => '__invoke'],
            ],
            ConfigKey::QUERY_HANDLERS => [
                ['message' => GetUserQuery::class, 'service' => GetUserHandler::class, 'method' => 'handle'],
            ],
            ConfigKey::COMMAND_LISTENERS => [
                ['message' => CreateUserCommand::class, 'service' => AuditListener::class, 'priority' => 10,
                 'events' => [\Componenta\CQRS\Command\Event\CommandProcessedEvent::class]],
            ],
        ];
    }
}
```

Register the core provider before application providers. Handler and listener services use normal DI autowiring or explicit factories. The shared DI service `cqrs.maps` contains three arrays: `command_handlers`, `query_handlers`, and `command_listeners`. Each locator receives its own section.

For direct construction, handlers are keyed by message name:

```php
$locator = new \Componenta\CQRS\Command\Locator\CommandHandlerLocator([
    CreateUserCommand::class => ['service' => CreateUserHandler::class, 'method' => '__invoke'],
], $container);

$command = new CreateUserCommand($email);
$handler = $locator->locateFor($command);
$handler($command); // Direct call when no additional arguments are required.
```

Identical handler registrations collapse; different handlers for one name throw `CqrsMapConflictException`. Identical explicit listener registrations throw `InvalidCqrsMapException`. Listeners run by descending priority, then service ID and canonical event list. An empty event list matches every command lifecycle event.

Names are resolved for each message instance. `supports()` does not instantiate a handler. Each `locateFor()` obtains services through the container, preserving the container's sharing rules. Listeners are filtered by event before service lookup.

[componenta/cqrs-app](https://github.com/componenta/cqrs-app) adds attribute discovery and `app:build`. A current file avoids discovery; a missing or invalid file falls back to source. Both paths use these same locators and registration rules.

## Name resolution and support

Command and query name resolvers implement the corresponding name-resolver interface. Their contracts are independent of handler registration:

- `supports(message)`: true when the resolver can determine the message name; false is a normal capability-check result. It does not establish that a handler is registered.
- `resolve(message)`: returns the name used for lookup, or throws the respective `UnsupportedCommandException` / `UnsupportedQueryException` for an unsupported message. Other resolution failures propagate unchanged.

The default resolvers support every object, using its explicit `NamedCommandInterface` / `NamedQueryInterface` name if provided, otherwise its PHP class name. Composite resolvers choose the first supporting delegate in configuration order, then the supporting fallback. If none supports the message, `supports()` returns false and `resolve()` throws the corresponding unsupported-message exception. A failure in a selected resolver does not trigger fallback.

The default array locators use an injected name resolver only when it supports the message. Otherwise they use the default naming rules. A locator's own `supports()` checks whether it owns the message (whether a matching registration exists in an array locator), without constructing or executing a handler. True does not guarantee successful service resolution. A composite locator delegates to its first supporting locator or its configured fallback and propagates failure from that selection.

## Failure contracts

The command and query buses propagate failures from the relevant processing stage without wrapping them. Handler lookup failures apply only if processing reaches the terminal handler:

| Situation | Exception |
| --- | --- |
| No matching command registration | `Command\Exception\HandlerNotFoundException` |
| No matching query registration | `Query\Exception\HandlerNotFoundException` |
| Mapped command service does not satisfy the callable descriptor | `Command\Exception\InvalidHandlerException` |
| Mapped query service does not satisfy the callable descriptor | `Query\Exception\InvalidHandlerException` |
| Invalid operation attributes or array query context | `InvalidArgumentException` |
| Mapped service missing from the container or failing to initialize | Original PSR-11 container exception |
| Callable preparation or argument resolution fails | Original invoker exception (`Componenta\DI\Exception\ExceptionInterface` for DI-owned failures) |
| Handler or middleware throws | Original escaping throwable |

CQRS exception names in this table are relative to `Componenta\CQRS`. `HandlerNotFoundException` carries the resolved logical message name; an absent DI service for an existing registration is a container failure, not a missing-handler registration.

A middleware can return without reaching the terminal handler. A query result of null is valid, and a command operation may have no result when processing is deferred. A completed synchronous command returning null has an `OperationResult` whose value is null. Neither bus converts a failure into a null result.

`EventMiddleware` may explicitly suppress listener-body errors when configured to do so. If notifying listeners of a downstream failure also fails, `CommandFailureNotificationException` preserves both exceptions. The bus itself does not catch or translate exceptions. Malformed registrations and invalid middleware configuration are rejected during map or pipeline construction, not reported as missing handler registrations.

## Metadata

`CommandMetadataProviderInterface` exposes one operation:

```php
$attribute = $metadata->get($command, MyAttribute::class);
```

The default `ReflectionCommandMetadataProvider` reads the command class independently of handler registration and maps. Each call creates a fresh attribute, including object arguments. An absent attribute or command class returns `null`. Invalid attribute declarations, repeated metadata, or construction failures throw `InvalidCommandMetadataException`; construction failures retain their original cause.

Retry, lock and transport middleware use this provider. Their attributes need no separate registration or compilation.

## Discovery attributes

`componenta/cqrs-app` understands:

```php
#[Componenta\CQRS\Command\Attribute\AsCommandHandler]
#[Componenta\CQRS\Command\Attribute\AsCommandListener(CreateUserCommand::class)]
#[Componenta\CQRS\Query\Attribute\AsQueryHandler]
```

A handler's message must occupy the first parameter slot. CQRS supplies that argument and delegates invocation to the configured `CallableInvokerInterface`. The default `CallableInvoker` does not inject missing arguments; a DI-aware custom invoker may supply additional dependencies. Attribute discovery itself does not perform argument injection.

Terminal handlers pass the callable returned by the locator and the message to the configured invoker unchanged. Callable interpretation, visibility checks, and argument resolution belong to the invoker. In DI 6, exact string service IDs take precedence over callable syntax. When native target identity is required, a locator can provide an unambiguous callable, such as a first-class callable or a public static method array.

## Optional packages

| Package | Responsibility |
|---|---|
| `componenta/cqrs-app` | Attribute discovery and explicit map building |
| `componenta/cqrs-policy` | Command/query authorization |
| `componenta/cqrs-retry` | Retry metadata and middleware |
| `componenta/cqrs-lock` | Resource locking |
| `componenta/cqrs-transaction-cycle` | Cycle Database transactions |
| `componenta/cqrs-transport` | Async transport contracts, serializers, middleware, worker |
| `componenta/cqrs-transport-cycle` | Cycle Database transport |
| `componenta/cqrs-transport-console` | Symfony Console worker |

## Verification

```bash
composer test
composer analyse
composer bench
```

CI runs Pest and PHPStan at maximum level on PHP 8.5. The package requires DI 6. Development CI can verify a pinned DI 6 source snapshot; release CI installs only the declared stable dependencies.
